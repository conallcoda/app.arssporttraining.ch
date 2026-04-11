<?php

use App\Models\Exercise\ExerciseProgram;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotStatusEnum;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns aggregate status counts for group program grid cells', function () {
    $admin = User::factory()->admin()->create();
    $athleteOne = User::factory()->athlete()->create();
    $athleteTwo = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $exerciseProgram = ExerciseProgram::factory()->create(['name' => 'Block 1 Endurance']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $exerciseProgram->id,
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athleteOne->id,
        'datetime' => Carbon::parse('2030-04-10 09:00:00'),
        'status' => TrainingProgramSlotStatusEnum::Completed,
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athleteTwo->id,
        'datetime' => Carbon::parse('2030-04-10 09:30:00'),
        'status' => TrainingProgramSlotStatusEnum::Skipped,
    ]);

    $response = $this->actingAs($admin)->getJson(route('api.program-grid-cells', [
        'group_id' => $group->id,
        'start' => '2030-04-07',
        'end' => '2030-04-13',
    ]));

    $response->assertOk()
        ->assertJsonPath("{$trainingProgram->id}-2030-04-10.count", 2)
        ->assertJsonPath("{$trainingProgram->id}-2030-04-10.completedCount", 1)
        ->assertJsonPath("{$trainingProgram->id}-2030-04-10.skippedCount", 1)
        ->assertJsonPath("{$trainingProgram->id}-2030-04-10.pendingCount", 0)
        ->assertJsonPath("{$trainingProgram->id}-2030-04-10.status", 'completed');
});

it('returns the slot status for user-specific program grid cells', function () {
    $admin = User::factory()->admin()->create();
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $exerciseProgram = ExerciseProgram::factory()->create(['name' => 'PH1 Day 1']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $exerciseProgram->id,
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-10 07:15:00'),
        'status' => TrainingProgramSlotStatusEnum::PartiallyCompleted,
    ]);

    $response = $this->actingAs($admin)->getJson(route('api.program-grid-cells', [
        'group_id' => $group->id,
        'user_id' => $athlete->id,
        'start' => '2030-04-07',
        'end' => '2030-04-13',
    ]));

    $response->assertOk()
        ->assertJsonPath("{$trainingProgram->id}-2030-04-10.count", 1)
        ->assertJsonPath("{$trainingProgram->id}-2030-04-10.time", '07:15')
        ->assertJsonPath("{$trainingProgram->id}-2030-04-10.status", 'partially_completed');
});
