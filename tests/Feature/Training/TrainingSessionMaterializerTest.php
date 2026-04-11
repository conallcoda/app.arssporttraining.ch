<?php

use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotExerciseStatusEnum;
use App\Models\Training\TrainingProgramSlotSetStatusEnum;
use App\Models\Training\TrainingProgramSlotStatusEnum;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('materializes planned exercises, sets, and values when a slot is created', function () {
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
            'settings' => ['reps', 'weight', 'tempo', 'rest', 'note'],
            'sets' => [
                'default' => 3,
                'label' => 'Set',
                'deload' => 'none',
            ],
            'reps' => [
                'mode' => 'manual',
                'default' => 6,
                'applyPer' => 'session',
            ],
            'weight' => [
                'mode' => 'manual',
                'default' => 82.5,
                'applyPer' => 'session',
            ],
            'tempo' => [
                'default' => '3010',
                'applyPer' => 'week',
            ],
            'rest' => [
                'default' => 120,
                'applyPer' => 'week',
            ],
            'note' => [
                'default' => 'Explode up',
                'applyPer' => 'week',
            ],
        ],
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'group' => 'A',
    ]);

    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-03 09:00:00'),
    ])->fresh();

    expect($slot->scheduled_date?->format('Y-m-d'))->toBe('2030-04-03')
        ->and($slot->status)->toBe(TrainingProgramSlotStatusEnum::Pending)
        ->and($slot->exercise_count)->toBe(1)
        ->and($slot->pending_exercise_count)->toBe(1)
        ->and($slot->compiled_at)->not->toBeNull()
        ->and($slot->exercises)->toHaveCount(1);

    $slotExercise = $slot->exercises()->firstOrFail();
    expect($slotExercise->status)->toBe(TrainingProgramSlotExerciseStatusEnum::Pending)
        ->and($slotExercise->set_count)->toBe(3)
        ->and($slotExercise->pending_set_count)->toBe(3)
        ->and($slotExercise->sets)->toHaveCount(3);

    $firstSet = $slotExercise->sets()->with('values')->orderBy('set_number')->firstOrFail();
    expect($firstSet->status)->toBe(TrainingProgramSlotSetStatusEnum::Pending)
        ->and($firstSet->values)->toHaveCount(5)
        ->and($firstSet->values->pluck('setting_key')->sort()->values()->all())->toBe(['note', 'reps', 'rest', 'tempo', 'weight']);

    expect($firstSet->values->firstWhere('setting_key', 'reps')?->planned_int_value)->toBe(6)
        ->and((float) $firstSet->values->firstWhere('setting_key', 'weight')?->planned_decimal_value)->toBe(82.5)
        ->and($firstSet->values->firstWhere('setting_key', 'tempo')?->planned_string_value)->toBe('3010')
        ->and($firstSet->values->firstWhere('setting_key', 'rest')?->planned_int_value)->toBe(120)
        ->and($firstSet->values->firstWhere('setting_key', 'note')?->planned_string_value)->toBe('Explode up');
});

it('rebuilds future slot materialization when the exercise program changes', function () {
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Friday Strength']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exerciseOne = Exercise::factory()->create([
        'name' => 'Front Squat',
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 2, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 5, 'applyPer' => 'session'],
        ],
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exerciseOne->id,
        'sort' => 0,
    ]);

    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-10 09:00:00'),
    ]);

    expect($slot->fresh()->exercise_count)->toBe(1);

    $exerciseTwo = Exercise::factory()->create([
        'name' => 'Split Squat',
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 8, 'applyPer' => 'session'],
        ],
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exerciseTwo->id,
        'sort' => 1,
    ]);

    $slot = $slot->fresh();

    expect($slot->exercise_count)->toBe(2)
        ->and($slot->exercises()->count())->toBe(2);
});
