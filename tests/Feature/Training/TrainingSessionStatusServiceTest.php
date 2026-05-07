<?php

use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotExerciseStatusEnum;
use App\Models\Training\TrainingProgramSlotSetStatusEnum;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Training\TrainingSessionStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('recomputes modification and completion state through a central exercise refresh when planned values change', function () {
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
            'reps' => ['mode' => 'manual', 'default' => 10, 'applyPer' => 'session'],
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
    ])->fresh('exercises.sets.values');

    $slotExercise = $slot->exercises->first();
    $slotSet = $slotExercise->sets->first();
    $value = $slotSet->values->firstWhere('setting_key', 'reps');

    $actualUpdate = match ($value->planned_value_type) {
        'int' => ['actual_value_type' => 'int', 'actual_int_value' => 5],
        'decimal' => ['actual_value_type' => 'decimal', 'actual_decimal_value' => 5.0],
        'json' => ['actual_value_type' => 'json', 'actual_json_value' => 5],
        default => ['actual_value_type' => 'string', 'actual_string_value' => '5'],
    };

    $value->update($actualUpdate + [
        'actual_is_explicit' => true,
    ]);
    $slotSet->update([
        'completed_at' => now(),
    ]);

    app(TrainingSessionStatusService::class)->refreshExerciseState($slotExercise);

    $slot = $slot->fresh('exercises.sets.values');
    $slotExercise = $slot->exercises->first();
    $slotSet = $slotExercise->sets->first();
    $value = $slotSet->values->firstWhere('setting_key', 'reps');

    expect($value->is_modified)->toBeTrue()
        ->and($slotSet->status)->toBe(TrainingProgramSlotSetStatusEnum::CompletedWithModification)
        ->and($slotExercise->status)->toBe(TrainingProgramSlotExerciseStatusEnum::Completed)
        ->and($slotExercise->modified_set_count)->toBe(1)
        ->and($slotExercise->has_any_modification)->toBeTrue();

    $plannedUpdate = match ($value->planned_value_type) {
        'int' => ['planned_int_value' => 5],
        'decimal' => ['planned_decimal_value' => 5.0],
        'json' => ['planned_json_value' => 5],
        default => ['planned_string_value' => '5'],
    };

    $value->update($plannedUpdate);

    app(TrainingSessionStatusService::class)->refreshExerciseState($slotExercise);

    $slot = $slot->fresh('exercises.sets.values');
    $slotExercise = $slot->exercises->first();
    $slotSet = $slotExercise->sets->first();
    $value = $slotSet->values->firstWhere('setting_key', 'reps');

    expect($value->is_modified)->toBeFalse()
        ->and($slotSet->status)->toBe(TrainingProgramSlotSetStatusEnum::Completed)
        ->and($slotExercise->status)->toBe(TrainingProgramSlotExerciseStatusEnum::Completed)
        ->and($slotExercise->modified_set_count)->toBe(0)
        ->and($slotExercise->has_any_modification)->toBeFalse()
        ->and($slot->has_any_modification)->toBeFalse();
});
