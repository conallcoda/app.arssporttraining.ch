<?php

use App\Data\Exercise\Settings\RepsSetting;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExercisePlanConfigOverride;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Training\ScheduledTrainingSnapshotResetService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('clears plan grid overrides and rebuilds scheduled slot values from canonical settings', function () {
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Friday Strength']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exercise = Exercise::factory()->create([
        'name' => 'Front Squat',
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 6, 'applyPer' => 'session'],
        ],
    ]);

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
    ]);

    $config = $program->config;
    $overrides = $config->defaultExerciseOverrides($pivot->id);
    $overrides->reps = RepsSetting::from([
        'mode' => 'manual',
        'default' => 8,
        'applyPer' => 'session',
    ]);
    $overrides->gridOverrides = [
        'sessions' => [],
        'cells' => [[
            'week' => 0,
            'session' => 0,
            'set' => 0,
            'data' => ['reps' => 10],
        ]],
    ];
    $config->setDefaultExerciseOverrides($pivot->id, $overrides);
    $program->config = $config;
    $program->save();

    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-03 09:00:00'),
    ])->fresh('exercises.sets.values');

    $value = $slot->exercises->first()->sets->first()->values->firstWhere('setting_key', 'reps');
    $value->update([
        'actual_value_type' => 'string',
        'actual_string_value' => '11',
        'actual_recorded_by' => $athlete->id,
        'actual_recorded_at' => now(),
        'actual_source' => 'athlete',
        'actual_is_explicit' => true,
        'is_modified' => true,
    ]);

    expect($value->fresh()->planned_string_value)->toBe('10')
        ->and(ExercisePlanConfigOverride::query()->count())->toBe(1);

    $result = app(ScheduledTrainingSnapshotResetService::class)->reset($trainingProgram->id, clearAllPlanGridOverrides: false);

    $slot = $slot->fresh('exercises.sets.values');
    $value = $slot->exercises->first()->sets->first()->values->firstWhere('setting_key', 'reps');
    $savedOverrides = $program->fresh()->config->defaultExerciseOverrides($pivot->id);

    expect($result)->toBe([
        'cleared_override_rows' => 1,
        'reset_slots' => 1,
    ])->and($value->planned_string_value)->toBe('8')
        ->and($value->actual_value_type)->toBeNull()
        ->and($value->actual_recorded_by)->toBeNull()
        ->and($value->actual_is_explicit)->toBeFalse()
        ->and($value->is_modified)->toBeFalse()
        ->and($slot->has_any_modification)->toBeFalse()
        ->and($savedOverrides->reps?->default)->toBe(8)
        ->and($savedOverrides->gridOverrides['cells'] ?? [])->toBeEmpty()
        ->and(ExercisePlanConfigOverride::query()->count())->toBe(0);
});
