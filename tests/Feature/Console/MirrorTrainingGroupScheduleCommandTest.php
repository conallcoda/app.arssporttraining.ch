<?php

use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramBlock;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Models\Training\TrainingProgramSlotSet;
use App\Models\Training\TrainingProgramSlotSetValue;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('mirrors a group schedule into a sandbox group', function () {
    $coach = User::factory()->coach()->create();
    $athlete = User::factory()->athlete()->create(['owner_id' => $coach->id]);

    $sourceGroup = UserGroup::create([
        'owner_id' => $coach->id,
        'name' => 'Armando',
    ]);

    $targetGroup = UserGroup::create([
        'owner_id' => $coach->id,
        'name' => 'Armando Test',
    ]);

    $sourceGroup->members()->attach($athlete->id);
    $targetGroup->members()->attach($athlete->id);

    $program = ExerciseProgram::factory()->create([
        'owner_id' => $coach->id,
        'name' => 'Bali Programm Armando',
    ]);
    $exercise = Exercise::factory()->create(['owner_id' => $coach->id]);

    $trainingProgram = TrainingProgram::create([
        'owner_id' => $coach->id,
        'group_id' => $sourceGroup->id,
        'exercise_program_id' => $program->id,
        'sort' => 2,
    ]);

    $program->update([
        'parent_type' => TrainingProgram::class,
        'parent_id' => $trainingProgram->id,
    ]);

    TrainingProgramBlock::create([
        'owner_id' => $coach->id,
        'group_id' => $sourceGroup->id,
        'user_id' => $athlete->id,
        'type' => 'category',
        'start' => '2026-05-09',
        'end' => '2026-06-08',
        'note' => 'INT. EXT BLOCK 1',
        'active' => true,
    ]);

    TrainingProgramSlot::create([
        'owner_id' => $coach->id,
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2026-05-09 08:00:00',
        'scheduled_date' => '2026-05-09',
        'status' => 'pending',
    ]);

    $slot = TrainingProgramSlot::query()->firstOrFail();
    $slotExercise = TrainingProgramSlotExercise::create([
        'training_program_slot_id' => $slot->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
        'status' => 'pending',
        'set_count' => 1,
        'pending_set_count' => 1,
    ]);
    $slotSet = TrainingProgramSlotSet::create([
        'training_program_slot_exercise_id' => $slotExercise->id,
        'set_number' => 1,
        'status' => 'pending',
    ]);
    TrainingProgramSlotSetValue::create([
        'training_program_slot_set_id' => $slotSet->id,
        'setting_key' => 'weight',
        'planned_value_type' => 'decimal',
        'planned_decimal_value' => 46.5,
        'unit' => 'kg',
    ]);

    $this->artisan('data:mirror-training-group-schedule', [
        'sourceGroupId' => $sourceGroup->id,
        'targetGroupId' => $targetGroup->id,
        '--replace' => true,
    ])->assertExitCode(0)
        ->expectsOutputToContain('Programs mirrored: 1');

    expect(TrainingProgram::query()->where('group_id', $targetGroup->id)->count())->toBe(1)
        ->and(TrainingProgramBlock::query()->where('group_id', $targetGroup->id)->count())->toBe(1)
        ->and(TrainingProgramSlot::query()
            ->whereHas('trainingProgram', fn ($query) => $query->where('group_id', $targetGroup->id))
            ->count())->toBe(1)
        ->and(TrainingProgramSlotSetValue::query()
            ->whereHas('slotSet.slotExercise.slot', fn ($query) => $query->whereHas('trainingProgram', fn ($inner) => $inner->where('group_id', $targetGroup->id)))
            ->value('planned_decimal_value'))->toBe(46.5);
});
