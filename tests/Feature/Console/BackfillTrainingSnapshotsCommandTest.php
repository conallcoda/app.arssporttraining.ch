<?php

use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('dry-runs eligible future-open backfills without mutating slots', function () {
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Backfill Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Backfill Strength']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['weight'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'weight' => ['mode' => 'manual', 'default' => 82.5, 'applyPer' => 'session'],
        ],
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
    ]);

    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-20 09:00:00'),
    ])->fresh('exercises.sets.values');

    $slot->exercises->first()->sets->first()->values->firstWhere('setting_key', 'weight')
        ->forceFill([
            'planned_decimal_value' => 85.0,
            'planned_json_value' => null,
        ])->save();

    $this->artisan('training:snapshot-backfill', ['--slot-id' => [$slot->id], '--future-only' => true])
        ->expectsOutputToContain('Dry run only.')
        ->expectsOutputToContain('Audited slots: 1')
        ->expectsOutputToContain('Eligible slots: 1')
        ->expectsOutputToContain('Rebuilt slots: 0')
        ->expectsOutputToContain('Skipped locked past: 0')
        ->expectsOutputToContain('Skipped ambiguous: 0')
        ->assertExitCode(0);

    $weight = $slot->fresh('exercises.sets.values')->exercises->first()->sets->first()->values->firstWhere('setting_key', 'weight');

    expect((float) $weight->planned_decimal_value)->toBe(85.0);
});

it('rematerializes eligible future-open mismatches when forced', function () {
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Backfill Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Backfill Strength']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['weight'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'weight' => ['mode' => 'manual', 'default' => 82.5, 'applyPer' => 'session'],
        ],
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
    ]);

    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-20 09:00:00'),
    ])->fresh('exercises.sets.values');

    $slot->exercises->first()->sets->first()->values->firstWhere('setting_key', 'weight')
        ->forceFill([
            'planned_decimal_value' => 85.0,
            'planned_json_value' => null,
        ])->save();

    $this->artisan('training:snapshot-backfill', ['--slot-id' => [$slot->id], '--future-only' => true, '--force' => true])
        ->expectsOutputToContain('Scheduled snapshot backfill complete.')
        ->expectsOutputToContain('Eligible slots: 1')
        ->expectsOutputToContain('Rebuilt slots: 1')
        ->assertExitCode(0);

    $weight = $slot->fresh('exercises.sets.values')->exercises->first()->sets->first()->values->firstWhere('setting_key', 'weight');

    expect((float) $weight->planned_decimal_value)->toBe(82.5);
});

it('skips locked-past mismatches during backfill so history is not silently rewritten', function () {
    Carbon::setTestNow(Carbon::parse('2030-04-21 09:00:00'));

    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Backfill Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Backfill Strength']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['weight'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'weight' => ['mode' => 'manual', 'default' => 82.5, 'applyPer' => 'session'],
        ],
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
    ]);

    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-20 09:00:00'),
    ])->fresh('exercises.sets.values');

    $slot->exercises->first()->sets->first()->values->firstWhere('setting_key', 'weight')
        ->forceFill([
            'planned_decimal_value' => 85.0,
            'planned_json_value' => null,
        ])->save();

    $this->artisan('training:snapshot-backfill', ['--slot-id' => [$slot->id], '--future-only' => true, '--force' => true])
        ->expectsOutputToContain('Eligible slots: 0')
        ->expectsOutputToContain('Skipped locked past: 1')
        ->expectsOutputToContain('Rebuilt slots: 0')
        ->assertExitCode(0);

    $weight = $slot->fresh('exercises.sets.values')->exercises->first()->sets->first()->values->firstWhere('setting_key', 'weight');

    expect((float) $weight->planned_decimal_value)->toBe(85.0);
});
