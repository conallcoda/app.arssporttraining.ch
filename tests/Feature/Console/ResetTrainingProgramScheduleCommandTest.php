<?php

use App\Data\Training\Config\ExerciseOverrides;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Tag;
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

it('reschedules a training program into the future and clears compiled historical noise', function () {
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Team Alpha']);
    $group->members()->attach($athlete->id);

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 12, 'applyPer' => 'session'],
        ],
    ]);

    $exerciseProgram = ExerciseProgram::factory()->create();
    $programExercise = ExerciseProgramExercise::create([
        'exercise_program_id' => $exerciseProgram->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
        'group' => 'A',
    ]);

    $trainingProgram = TrainingProgram::create([
        'group_id' => $group->id,
        'exercise_program_id' => $exerciseProgram->id,
        'sort' => 0,
    ]);

    $exerciseProgram->update([
        'parent_type' => TrainingProgram::class,
        'parent_id' => $trainingProgram->id,
    ]);

    $category = Tag::create(['name' => 'Strength', 'scope' => 'exercise_category']);

    $block = TrainingProgramBlock::create([
        'group_id' => $group->id,
        'category_id' => $category->id,
        'type' => 'category',
        'start' => '2026-06-02',
        'end' => '2026-07-02',
        'note' => 'Block 1 STR',
        'active' => true,
    ]);

    foreach (['2026-06-02 09:00:00', '2026-06-04 09:00:00'] as $dateTime) {
        $slot = TrainingProgramSlot::withoutEvents(fn () => TrainingProgramSlot::create([
            'training_program_id' => $trainingProgram->id,
            'user_id' => $athlete->id,
            'datetime' => $dateTime,
            'scheduled_date' => substr($dateTime, 0, 10),
            'status' => 'completed',
            'compiled_at' => '2026-06-12 10:00:00',
            'compiled_version' => 'stale',
            'exercise_count' => 1,
            'completed_exercise_count' => 1,
            'has_any_modification' => true,
            'completed_at' => '2026-06-12 10:00:00',
        ]));

        $slotExercise = TrainingProgramSlotExercise::create([
            'training_program_slot_id' => $slot->id,
            'exercise_id' => $exercise->id,
            'sort' => 0,
            'type' => 'main',
            'group' => 'A',
            'status' => 'completed',
            'set_count' => 1,
            'completed_set_count' => 1,
            'has_any_modification' => true,
        ]);

        $slotSet = TrainingProgramSlotSet::create([
            'training_program_slot_exercise_id' => $slotExercise->id,
            'set_number' => 1,
            'status' => 'completed',
            'has_any_modification' => true,
        ]);

        TrainingProgramSlotSetValue::create([
            'training_program_slot_set_id' => $slotSet->id,
            'setting_key' => 'reps',
            'planned_value_type' => 'int',
            'planned_int_value' => 12,
            'actual_value_type' => 'int',
            'actual_int_value' => 10,
            'is_modified' => true,
        ]);
    }

    $config = $exerciseProgram->config;
    $overrides = ExerciseOverrides::from([
        'historicalGridOverrides' => [
            'sessions' => [
                ['week' => 0, 'session' => 0, 'data' => ['sets' => 1]],
            ],
            'cells' => [
                ['week' => 0, 'session' => 0, 'set' => 0, 'data' => ['reps' => 10]],
            ],
        ],
    ]);
    $config->setUserExerciseOverrides($athlete->id, $programExercise->id, $overrides);
    $exerciseProgram->config = $config;
    $exerciseProgram->saveQuietly();

    $this->artisan('training:reset-program-schedule', [
        'trainingProgramId' => $trainingProgram->id,
        '--block' => $block->id,
        '--first-session' => '2026-06-14',
    ])->assertExitCode(0)
        ->expectsOutputToContain('Offset days: 12')
        ->expectsOutputToContain('Slots reset/offset: 2');

    expect($block->fresh()->start?->toDateString())->toBe('2026-06-14')
        ->and($block->fresh()->end?->toDateString())->toBe('2026-07-14')
        ->and(TrainingProgramSlot::query()->orderBy('datetime')->pluck('scheduled_date')->map->toDateString()->all())
        ->toBe(['2026-06-14', '2026-06-16'])
        ->and(TrainingProgramSlotExercise::query()->count())->toBe(0)
        ->and(TrainingProgramSlotSet::query()->count())->toBe(0)
        ->and(TrainingProgramSlotSetValue::query()->count())->toBe(0);

    $slot = TrainingProgramSlot::query()->orderBy('datetime')->firstOrFail();

    expect($slot->compiled_at)->toBeNull()
        ->and($slot->compiled_version)->toBeNull()
        ->and($slot->status->value)->toBe('pending')
        ->and($slot->exercise_count)->toBe(0)
        ->and($slot->has_any_modification)->toBeFalse()
        ->and($slot->completed_at)->toBeNull();

    $savedOverrides = $exerciseProgram->fresh()->config->userExerciseOverrides($athlete->id, $programExercise->id);

    expect($savedOverrides->historicalGridOverrides)->toBe([
        'sessions' => [],
        'cells' => [],
    ]);
});
