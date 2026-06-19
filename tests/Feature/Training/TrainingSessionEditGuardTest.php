<?php

use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Models\Training\TrainingProgramSlotExerciseStatusEnum;
use App\Models\Training\TrainingProgramSlotSet;
use App\Models\Training\TrainingProgramSlotSetStatusEnum;
use App\Models\Training\TrainingProgramSlotStatusEnum;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Training\TrainingSessionEditGuard;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('counts only recorded slots as immutable', function () {
    Carbon::setTestNow('2030-04-12 12:00:00');

    $group = UserGroup::create(['name' => 'Test Group']);
    $athlete = User::factory()->athlete()->create();
    $exerciseProgram = ExerciseProgram::factory()->create();
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $exerciseProgram->id,
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-10 09:00:00'),
        'status' => TrainingProgramSlotStatusEnum::Pending,
    ]);

    $recordedPastSlot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-11 09:00:00'),
        'status' => TrainingProgramSlotStatusEnum::Completed,
        'completed_at' => Carbon::parse('2030-04-11 10:00:00'),
    ]);
    $recordedPastSlot->forceFill([
        'exercise_count' => 1,
        'completed_exercise_count' => 1,
        'pending_exercise_count' => 0,
    ])->save();

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-13 09:00:00'),
        'status' => TrainingProgramSlotStatusEnum::Pending,
    ]);

    $recordedFutureSlot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-14 09:00:00'),
        'status' => TrainingProgramSlotStatusEnum::Completed,
        'completed_at' => Carbon::parse('2030-04-12 10:00:00'),
    ]);
    $recordedFutureSlot->forceFill([
        'exercise_count' => 1,
        'completed_exercise_count' => 1,
        'pending_exercise_count' => 0,
    ])->save();

    $guard = app(TrainingSessionEditGuard::class);

    expect($guard->countImmutableSlotsForTrainingProgram($trainingProgram->id))->toBe(2)
        ->and($guard->countImmutableSlotsForExerciseProgram($exerciseProgram->id))->toBe(2)
        ->and($guard->countImmutableSlotsForOccurrence($trainingProgram->id, '2030-04-10 09:00:00', $athlete->id))->toBe(0)
        ->and($guard->countImmutableSlotsForOccurrence($trainingProgram->id, '2030-04-11 09:00:00', $athlete->id))->toBe(1)
        ->and($guard->countImmutableSlotsForOccurrence($trainingProgram->id, '2030-04-13 09:00:00', $athlete->id))->toBe(0)
        ->and($guard->countImmutableSlotsForOccurrence($trainingProgram->id, '2030-04-14 09:00:00', $athlete->id))->toBe(1);
});

it('locks only recorded slots for plan editing', function () {
    Carbon::setTestNow('2030-04-12 12:00:00');

    $group = UserGroup::create(['name' => 'Test Group']);
    $athlete = User::factory()->athlete()->create();
    $exerciseProgram = ExerciseProgram::factory()->create();
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $exerciseProgram->id,
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-10 09:00:00'),
        'status' => TrainingProgramSlotStatusEnum::Pending,
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-13 09:00:00'),
        'status' => TrainingProgramSlotStatusEnum::Pending,
    ]);

    $recordedFutureSlot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-14 09:00:00'),
        'status' => TrainingProgramSlotStatusEnum::Completed,
        'completed_at' => Carbon::parse('2030-04-12 10:00:00'),
    ]);
    $recordedFutureSlot->forceFill([
        'exercise_count' => 1,
        'completed_exercise_count' => 1,
        'pending_exercise_count' => 0,
    ])->save();

    $lookup = app(TrainingSessionEditGuard::class)->planEditLockedDateTimeLookup(
        TrainingProgramSlot::query()->where('training_program_id', $trainingProgram->id),
    );

    expect($lookup)->not->toHaveKey('2030-04-10 09:00:00')
        ->and($lookup)->not->toHaveKey('2030-04-13 09:00:00')
        ->and($lookup)->toHaveKey('2030-04-14 09:00:00');
});

it('locks future plan editing when child rows show recorded state despite a pending slot aggregate', function () {
    Carbon::setTestNow('2030-04-12 12:00:00');

    $athlete = User::factory()->athlete()->create();
    $exercise = Exercise::factory()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $exerciseProgram = ExerciseProgram::factory()->create();
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $exerciseProgram->id,
    ]);

    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-14 09:00:00'),
        'status' => TrainingProgramSlotStatusEnum::Pending,
        'completed_exercise_count' => 0,
        'partial_exercise_count' => 0,
        'skipped_exercise_count' => 0,
        'has_any_modification' => false,
        'completed_at' => null,
    ]);

    $slotExercise = TrainingProgramSlotExercise::create([
        'training_program_slot_id' => $slot->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
        'status' => TrainingProgramSlotExerciseStatusEnum::Pending,
        'set_count' => 1,
        'completed_set_count' => 0,
        'modified_set_count' => 0,
        'skipped_set_count' => 0,
        'pending_set_count' => 1,
        'has_any_modification' => false,
    ]);

    TrainingProgramSlotSet::create([
        'training_program_slot_exercise_id' => $slotExercise->id,
        'set_number' => 1,
        'status' => TrainingProgramSlotSetStatusEnum::Completed,
        'completed_at' => Carbon::parse('2030-04-12 10:00:00'),
        'has_any_modification' => false,
    ]);

    $lookup = app(TrainingSessionEditGuard::class)->planEditLockedDateTimeLookup(
        TrainingProgramSlot::query()->whereKey($slot->id),
    );

    expect($lookup)->toHaveKey('2030-04-14 09:00:00')
        ->and(app(TrainingSessionEditGuard::class)->countImmutableSlotsForTrainingProgram($trainingProgram->id))->toBe(1);
});
