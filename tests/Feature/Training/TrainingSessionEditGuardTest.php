<?php

use App\Models\Exercise\ExerciseProgram;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotStatusEnum;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Training\TrainingSessionEditGuard;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('counts only recorded slots as immutable', function () {
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
        'datetime' => Carbon::parse('2030-04-11 09:00:00'),
        'status' => TrainingProgramSlotStatusEnum::Completed,
        'completed_at' => Carbon::parse('2030-04-11 10:00:00'),
    ]);

    $guard = app(TrainingSessionEditGuard::class);

    expect($guard->countImmutableSlotsForTrainingProgram($trainingProgram->id))->toBe(1)
        ->and($guard->countImmutableSlotsForExerciseProgram($exerciseProgram->id))->toBe(1)
        ->and($guard->countImmutableSlotsForOccurrence($trainingProgram->id, '2030-04-10 09:00:00', $athlete->id))->toBe(0)
        ->and($guard->countImmutableSlotsForOccurrence($trainingProgram->id, '2030-04-11 09:00:00', $athlete->id))->toBe(1);
});
