<?php

use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Athlete\MetricSubmission;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Training\TrainingSessionMaterializer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('materializes automatic heart rate ranges as strings on athlete sessions', function () {
    $athlete = User::factory()->athlete()->create();
    $coach = User::factory()->coach()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Ergo Bike']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exercise = Exercise::factory()->create([
        'name' => 'Ergo Bike',
        'config' => [
            'settings' => ['duration', 'watts', 'heartRate', 'heartRateZone'],
            'sets' => ['default' => 5, 'label' => 'Set', 'deload' => 'none'],
            'duration' => ['mode' => 'manual', 'default' => 60, 'applyPer' => 'session'],
            'watts' => ['mode' => 'manual', 'default' => 100, 'applyPer' => 'session'],
            'heartRate' => ['mode' => 'automatic_jogging', 'applyPer' => 'session'],
            'heartRateZone' => ['mode' => 'manual', 'default' => '0', 'applyPer' => 'session'],
        ],
    ]);

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $config = $program->config;
    $config->setDefaultExerciseOverrides($pivot->id, \App\Data\Training\Config\ExerciseOverrides::from([
        'gridOverrides' => [
            'cells' => [
                ['week' => 0, 'session' => 0, 'set' => 0, 'data' => ['heartRateZone' => '0']],
                ['week' => 0, 'session' => 0, 'set' => 1, 'data' => ['heartRateZone' => '1']],
                ['week' => 0, 'session' => 0, 'set' => 2, 'data' => ['heartRateZone' => '2']],
                ['week' => 0, 'session' => 0, 'set' => 3, 'data' => ['heartRateZone' => '3']],
                ['week' => 0, 'session' => 0, 'set' => 4, 'data' => ['heartRateZone' => '4']],
            ],
            'sessions' => [],
        ],
    ]));
    $program->config = $config;
    $program->saveQuietly();

    $heartRate = MetricSubmission::query()->create([
        'user_id' => $athlete->id,
        'metric' => \App\Data\Athlete\Metric\MetricEnum::HeartRate,
        'recorded_by' => $coach->id,
        'recorded_at' => '2026-05-03',
        'owner_type' => null,
        'owner_id' => null,
    ]);
    $heartRate->values()->createMany([
        ['field' => 'heartRate', 'value' => '193'],
        ['field' => 'anaerobicThreshold', 'value' => '90'],
    ]);

    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2026-05-04 09:00:00',
    ])->fresh();

    app(TrainingSessionMaterializer::class)->materialize($slot, force: true);

    $slot = $slot->fresh('exercises.sets.values');
    $sets = $slot->exercises->firstOrFail()->sets->sortBy('set_number')->values();

    expect($sets[0]->values->firstWhere('setting_key', 'heartRate')?->planned_value_type)->toBe('string')
        ->and($sets[0]->values->firstWhere('setting_key', 'heartRate')?->planned_string_value)->toBe('106-134')
        ->and($sets[0]->values->firstWhere('setting_key', 'heartRate')?->planned_json_value)->toBe([
            'kind' => 'heart_rate',
            'format' => 'range',
            'display' => '106-134',
            'min' => 106,
            'max' => 134,
        ])
        ->and($sets[4]->values->firstWhere('setting_key', 'heartRate')?->planned_string_value)->toBe('193-203');
});
