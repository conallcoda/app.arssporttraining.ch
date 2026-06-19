<?php

use App\Data\Exercise\Settings\RepsSetting;
use App\Data\Training\Config\ExerciseOverrides;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Training\TrainingSessionMaterializer;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rematerializes an already-compiled past slot when it has no recorded data', function () {
    Carbon::setTestNow('2026-04-17 12:00:00');

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
            'reps' => ['mode' => 'manual', 'default' => 5, 'applyPer' => 'session'],
        ],
    ]);

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
    ]);

    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2026-04-10 09:00:00'),
    ])->fresh();

    $originalCompiledAt = $slot->compiled_at;
    $originalRepsValue = $slot->exercises()
        ->with('sets.values')
        ->firstOrFail()
        ->sets
        ->firstOrFail()
        ->values
        ->firstWhere('setting_key', 'reps');

    $config = $program->config;
    $config->setDefaultExerciseOverrides(
        $pivot->id,
        new ExerciseOverrides(
            reps: RepsSetting::from(['mode' => 'manual', 'default' => 8, 'applyPer' => 'session']),
        ),
    );
    $program->config = $config;
    $program->save();

    app(TrainingSessionMaterializer::class)->materialize($slot->fresh(), force: true);

    $slot = $slot->fresh();
    $repsValue = $slot->exercises()
        ->with('sets.values')
        ->firstOrFail()
        ->sets
        ->firstOrFail()
        ->values
        ->firstWhere('setting_key', 'reps');

    expect($originalRepsValue?->planned_value_type)->toBe('string')
        ->and($originalRepsValue?->planned_string_value)->toBe('5')
        ->and($repsValue?->planned_value_type)->toBe('string')
        ->and($repsValue?->planned_string_value)->toBe('8')
        ->and($slot->compiled_at)->not->toBeNull();
});
