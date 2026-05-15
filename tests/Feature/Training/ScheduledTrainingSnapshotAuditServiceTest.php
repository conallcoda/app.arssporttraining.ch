<?php

use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Training\ScheduledTrainingSnapshotAuditService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('reports a materialized slot as matching when the stored snapshot still matches compilation', function () {
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Audit Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Audit Strength']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exercise = Exercise::factory()->create([
        'name' => 'Front Squat',
        'config' => [
            'settings' => ['reps', 'weight', 'tempo'],
            'sets' => ['default' => 2, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 6, 'applyPer' => 'session'],
            'weight' => ['mode' => 'manual', 'default' => 82.5, 'applyPer' => 'session'],
            'tempo' => ['default' => '3010', 'applyPer' => 'session'],
        ],
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-03 09:00:00'),
    ]);

    $result = app(ScheduledTrainingSnapshotAuditService::class)->audit($slot->fresh());

    expect($result->slotId)->toBe($slot->id)
        ->and($result->classification->kind)->toBe('future_open')
        ->and(collect($result->mismatches)->map(fn ($mismatch) => [$mismatch->path, $mismatch->expected, $mismatch->actual])->all())->toBe([])
        ->and($result->mismatchCount)->toBe(0)
        ->and($result->matches)->toBeTrue();
});

it('detects when a stored planned value drifts away from the compiled snapshot', function () {
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Audit Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Audit Strength']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exercise = Exercise::factory()->create([
        'name' => 'Front Squat',
        'config' => [
            'settings' => ['reps', 'weight'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 6, 'applyPer' => 'session'],
            'weight' => ['mode' => 'manual', 'default' => 82.5, 'applyPer' => 'session'],
        ],
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-03 09:00:00'),
    ])->fresh('exercises.sets.values');

    $weight = $slot->exercises->first()->sets->first()->values->firstWhere('setting_key', 'weight');
    $weight->forceFill([
        'planned_decimal_value' => 85.0,
        'planned_json_value' => null,
    ])->save();

    $result = app(ScheduledTrainingSnapshotAuditService::class)->audit($slot->fresh());

    expect($result->matches)->toBeFalse()
        ->and($result->classification->kind)->toBe('future_open')
        ->and($result->mismatchCount)->toBeGreaterThan(0)
        ->and(collect($result->mismatches)->map(fn ($mismatch) => $mismatch->path)->all())
        ->toContain('exercise:'.$exercise->id.'|0||main.set:1.value:weight.planned_decimal_value')
        ->and(collect($result->mismatches)->firstWhere('path', 'exercise:'.$exercise->id.'|0||main.set:1.value:weight.planned_decimal_value')?->expected)->toBe(82.5)
        ->and(collect($result->mismatches)->firstWhere('path', 'exercise:'.$exercise->id.'|0||main.set:1.value:weight.planned_decimal_value')?->actual)->toBe(85.0);
});

it('classifies immutable or edited slots so migration tooling can avoid unsafe rewrites', function () {
    Carbon::setTestNow(Carbon::parse('2030-04-10 09:00:00'));

    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Audit Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Audit Strength']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 6, 'applyPer' => 'session'],
        ],
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
    ]);

    $lockedPastSlot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-03 09:00:00'),
    ]);

    $ambiguousFutureSlot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-13 09:00:00'),
    ])->fresh('exercises.sets.values');

    $value = $ambiguousFutureSlot->exercises->first()->sets->first()->values->firstWhere('setting_key', 'reps');
    $value->update([
        'actual_value_type' => 'string',
        'actual_string_value' => '7',
        'actual_recorded_by' => $athlete->id,
        'actual_recorded_at' => now(),
        'actual_source' => 'athlete',
        'actual_is_explicit' => true,
        'is_modified' => true,
    ]);

    $lockedResult = app(ScheduledTrainingSnapshotAuditService::class)->audit($lockedPastSlot->fresh());
    $ambiguousResult = app(ScheduledTrainingSnapshotAuditService::class)->audit($ambiguousFutureSlot->fresh());

    expect($lockedResult->classification->kind)->toBe('locked_past')
        ->and($lockedResult->classification->reasons)->toContain('datetime_in_past')
        ->and($ambiguousResult->classification->kind)->toBe('ambiguous_boundary')
        ->and($ambiguousResult->classification->reasons)->toContain('actual_values_present');
});
