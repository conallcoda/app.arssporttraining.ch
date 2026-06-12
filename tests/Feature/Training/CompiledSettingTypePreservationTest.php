<?php

use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Training\TrainingSessionMaterializer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('preserves string-shaped planned values during materialization', function () {
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Mixed Types']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exercise = Exercise::factory()->create([
        'name' => 'Mixed Types Exercise',
        'config' => [
            'settings' => ['reps', 'duration', 'heartRate', 'heartRateZone', 'pace', 'tempo', 'weight'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => '12_12', 'applyPer' => 'session'],
            'duration' => ['unit' => 'mm:ss', 'default' => '1:00', 'applyPer' => 'session'],
            'heartRate' => ['mode' => 'manual', 'default' => '98-126', 'applyPer' => 'session'],
            'heartRateZone' => ['default' => '0-4', 'applyPer' => 'session'],
            'pace' => ['default' => '5:00', 'applyPer' => 'session'],
            'tempo' => ['default' => '3010', 'applyPer' => 'week'],
            'weight' => ['mode' => 'manual', 'default' => 32.5, 'applyPer' => 'session'],
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
        'datetime' => '2026-05-04 09:00:00',
    ])->fresh();

    app(TrainingSessionMaterializer::class)->materialize($slot, force: true);

    $values = $slot->fresh('exercises.sets.values')
        ->exercises
        ->firstOrFail()
        ->sets
        ->firstOrFail()
        ->values
        ->keyBy('setting_key');

    expect($values['reps']->planned_value_type)->toBe('string')
        ->and($values['reps']->planned_string_value)->toBe('12_12')
        ->and($values['reps']->planned_json_value)->toBe([
            'kind' => 'reps',
            'format' => 'split',
            'display' => '12L_12R',
            'total' => 24,
            'parts' => [12, 12],
            'is_bilateral' => true,
            'bilateral_execution' => 'alternating',
        ])
        ->and($values['duration']->planned_value_type)->toBe('int')
        ->and($values['duration']->planned_int_value)->toBe(60)
        ->and($values['heartRate']->planned_value_type)->toBe('string')
        ->and($values['heartRate']->planned_string_value)->toBe('98-126')
        ->and($values['heartRate']->planned_json_value)->toBe([
            'kind' => 'heart_rate',
            'format' => 'range',
            'display' => '98-126',
            'min' => 98,
            'max' => 126,
        ])
        ->and($values['heartRateZone']->planned_value_type)->toBe('string')
        ->and($values['heartRateZone']->planned_string_value)->toBe('0-4')
        ->and($values['heartRateZone']->planned_json_value)->toBe([
            'kind' => 'heart_rate_zone',
            'format' => 'range',
            'display' => '0-4',
            'min' => 0,
            'max' => 4,
        ])
        ->and($values['pace']->planned_value_type)->toBe('string')
        ->and($values['pace']->planned_string_value)->toBe('5:00')
        ->and($values['tempo']->planned_value_type)->toBe('string')
        ->and($values['tempo']->planned_string_value)->toBe('3010')
        ->and($values['weight']->planned_value_type)->toBe('decimal')
        ->and((float) $values['weight']->planned_decimal_value)->toBe(32.5);
});
