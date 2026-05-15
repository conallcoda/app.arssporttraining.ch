<?php

use App\Models\Athlete\MetricSubmission;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Tag;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramBlock;
use App\Models\Training\TrainingProgramBlockTypeEnum;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Support\Training\ProgramPreviewBuilder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('builds exact preview values for a concrete session from the compiler boundary', function () {
    $program = ExerciseProgram::factory()->create(['name' => 'Preview Strength']);

    $exercise = Exercise::factory()->create([
        'name' => 'Front Squat',
        'config' => [
            'settings' => ['reps', 'weight', 'tempo', 'rest'],
            'sets' => ['default' => 3, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 8, 'applyPer' => 'set'],
            'weight' => ['mode' => 'manual', 'default' => 82.5, 'applyPer' => 'set'],
            'tempo' => ['default' => '3010', 'applyPer' => 'session'],
            'rest' => ['default' => 60, 'applyPer' => 'session'],
        ],
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $preview = app(ProgramPreviewBuilder::class)->build(
        exerciseProgram: $program->fresh(),
        weeks: 1,
        sessionsPerWeek: 1,
        weekSessionDates: [['2026-04-27']],
    );

    $session = $preview['sessions']['0-0'];
    $exerciseView = $session['exercisesBySection']['main'][0];

    expect($exerciseView->name)->toBe('Front Squat')
        ->and($exerciseView->setCount)->toBe(3)
        ->and($exerciseView->sessionRows[0]->label)->toBe('Reps')
        ->and($exerciseView->sessionRows[0]->values)->toBe(['8', '8', '8'])
        ->and($exerciseView->sessionRows[1]->label)->toBe('Weight (kg)')
        ->and($exerciseView->sessionRows[1]->values)->toBe(['82.5', '82.5', '82.5'])
        ->and($exerciseView->sessionRows[2]->label)->toBe('Tempo')
        ->and($exerciseView->sessionRows[2]->values)->toBe(['3010', '3010', '3010'])
        ->and($exerciseView->sessionRows[3]->label)->toBe('Rest (s)')
        ->and($exerciseView->sessionRows[3]->values)->toBe(['60', '60', '60']);
});

it('omits metric-dependent preview exercises when required inputs are missing', function () {
    $program = ExerciseProgram::factory()->create(['name' => 'Preview Metrics']);

    $autoWeightExercise = Exercise::factory()->create([
        'name' => 'Auto Front Squat',
        'config' => [
            'settings' => ['reps', 'weight'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 8, 'applyPer' => 'session'],
            'weight' => ['mode' => 'automatic', 'oneRepMaxModifier' => 100, 'applyPer' => 'session'],
        ],
    ]);

    $manualExercise = Exercise::factory()->create([
        'name' => 'Manual Split Squat',
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 10, 'applyPer' => 'session'],
        ],
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $autoWeightExercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);
    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $manualExercise->id,
        'sort' => 1,
        'type' => 'main',
    ]);

    $preview = app(ProgramPreviewBuilder::class)->build(
        exerciseProgram: $program->fresh(),
        weeks: 1,
        sessionsPerWeek: 1,
        weekSessionDates: [['2026-04-27']],
        planMeasuredReps: null,
        planMeasuredWeight: null,
    );

    $exerciseNames = collect($preview['sessions']['0-0']['exercisesBySection']['main'] ?? [])
        ->map(fn ($exerciseView) => $exerciseView->name)
        ->all();

    expect($exerciseNames)->toBe(['Manual Split Squat']);
});

it('matches materialized scheduled values for automatic progression across the same timeline', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-01 09:00:00'));

    $athlete = User::factory()->athlete()->create();
    $coach = User::factory()->coach()->create();
    $group = UserGroup::create(['name' => 'Preview Strength Group']);
    $category = Tag::factory()->withScope('training_category')->create(['name' => 'Strength']);
    $program = ExerciseProgram::factory()->create([
        'name' => 'Preview Strength Progression',
        'exercise_category_id' => $category->id,
    ]);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exercise = Exercise::factory()->create([
        'name' => 'Front Squat',
        'config' => [
            'settings' => ['reps', 'weight', 'tempo', 'rest'],
            'sets' => ['default' => 4, 'label' => 'Set', 'deload' => 'odd', 'deloadBy' => 1],
            'reps' => [
                'mode' => 'automatic',
                'default' => 14,
                'stepDownInterval' => 2,
                'decrement' => 2,
                'minimum' => 1,
                'applyPer' => 'set',
            ],
            'weight' => [
                'mode' => 'automatic',
                'oneRepMaxModifier' => 85,
                'default' => 5,
                'applyPer' => 'set',
            ],
            'tempo' => ['default' => '3010', 'applyPer' => 'week'],
            'rest' => ['default' => 60, 'applyPer' => 'week'],
            'preview' => ['weeks' => 5, 'sessionsPerWeek' => 1],
        ],
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    TrainingProgramBlock::create([
        'group_id' => $group->id,
        'user_id' => null,
        'category_id' => $category->id,
        'type' => TrainingProgramBlockTypeEnum::Category,
        'start' => '2026-05-09',
        'end' => '2026-06-08',
        'note' => 'Strength Block',
        'active' => true,
        'config' => ['goal' => 5, 'autoRecord1rm' => false],
    ]);

    $metric = MetricSubmission::query()->create([
        'user_id' => $athlete->id,
        'metric' => \App\Data\Athlete\Metric\MetricEnum::OneRepMax,
        'recorded_by' => $coach->id,
        'recorded_at' => '2026-05-09',
        'owner_type' => null,
        'owner_id' => null,
    ]);
    $metric->values()->createMany([
        ['field' => 'measuredReps', 'value' => '1'],
        ['field' => 'measuredWeight', 'value' => '93'],
        ['field' => 'estimated1RM', 'value' => '93'],
    ]);

    $weekSessionDates = [
        ['2026-05-09'],
        ['2026-05-11', '2026-05-12', '2026-05-15'],
        ['2026-05-18', '2026-05-22'],
        ['2026-05-25', '2026-05-29'],
        ['2026-06-01', '2026-06-05'],
    ];

    $slotDateTimes = collect($weekSessionDates)
        ->flatten(1)
        ->map(fn (string $date, int $index) => Carbon::parse($date.' '.match ($index % 3) {
            0 => '08:00:00',
            1 => '11:00:00',
            default => '14:00:00',
        }));

    $slots = $slotDateTimes->map(fn (Carbon $dateTime) => TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => $dateTime,
    ]));

    $preview = app(ProgramPreviewBuilder::class)->build(
        exerciseProgram: $program->fresh(),
        weeks: 5,
        sessionsPerWeek: 3,
        weekSessionDates: $weekSessionDates,
        planMeasuredReps: 1,
        planMeasuredWeight: 93.0,
        planTargetGoal: 5,
    );

    $firstPreviewExercise = $preview['sessions']['0-0']['exercisesBySection']['main'][0];
    $thirdPreviewExercise = $preview['sessions']['1-1']['exercisesBySection']['main'][0];

    $firstPreviewWeightRow = collect($firstPreviewExercise->sessionRows)->firstWhere('label', 'Weight (kg)');
    $thirdPreviewWeightRow = collect($thirdPreviewExercise->sessionRows)->firstWhere('label', 'Weight (kg)');

    $firstSlotWeights = $slots[0]->fresh('exercises.sets.values')
        ->exercises->first()->sets
        ->map(fn ($set) => (float) $set->values->firstWhere('setting_key', 'weight')->planned_decimal_value)
        ->values()
        ->all();

    $thirdSlotWeights = $slots[2]->fresh('exercises.sets.values')
        ->exercises->first()->sets
        ->map(fn ($set) => (float) $set->values->firstWhere('setting_key', 'weight')->planned_decimal_value)
        ->values()
        ->all();

    expect($firstPreviewWeightRow?->values)->toBe(['44', '46.5', '49'])
        ->and($thirdPreviewWeightRow?->values)->toBe(['44', '46.5', '49', '51.5'])
        ->and($firstSlotWeights)->toBe([44.0, 46.5, 49.0])
        ->and($thirdSlotWeights)->toBe([44.0, 46.5, 49.0, 51.5])
        ->and($firstPreviewWeightRow?->values)->toBe(array_map('strval', $firstSlotWeights))
        ->and($thirdPreviewWeightRow?->values)->toBe(array_map('strval', $thirdSlotWeights));
});
