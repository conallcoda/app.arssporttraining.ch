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
use App\Training\TrainingSessionCompiler;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('keeps sessions in the same calendar week on the same resolved training week', function () {
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Strength']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps', 'rest'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 6, 'applyPer' => 'session'],
            'rest' => ['default' => 60, 'applyPer' => 'week'],
            'preview' => ['weeks' => 2, 'sessionsPerWeek' => 2],
        ],
    ]);

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
    ]);

    $config = $program->config;
    $overrides = $config->defaultExerciseOverrides($pivot->id);
    $overrides->gridOverrides = [
        'cells' => [],
        'weeks' => [
            ['week' => 1, 'data' => ['rest' => 90]],
        ],
    ];
    $config->setDefaultExerciseOverrides($pivot->id, $overrides);
    $program->config = $config;
    $program->save();

    $firstSlot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2026-04-28 09:00:00'),
    ]);

    $secondSlot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2026-04-30 09:00:00'),
    ]);

    $thirdSlot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2026-05-05 09:00:00'),
    ]);

    $firstRest = $firstSlot->fresh('exercises.sets.values')
        ->exercises->first()->sets->first()->values->firstWhere('setting_key', 'rest');
    $secondRest = $secondSlot->fresh('exercises.sets.values')
        ->exercises->first()->sets->first()->values->firstWhere('setting_key', 'rest');
    $thirdRest = $thirdSlot->fresh('exercises.sets.values')
        ->exercises->first()->sets->first()->values->firstWhere('setting_key', 'rest');

    expect($firstRest?->planned_int_value)->toBe(60)
        ->and($secondRest?->planned_int_value)->toBe(60)
        ->and($thirdRest?->planned_int_value)->toBe(90);
});

it('compiles automatic progression using the full active block session shape', function () {
    $athlete = User::factory()->athlete()->create();
    $coach = User::factory()->coach()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $category = Tag::factory()->withScope('training_category')->create(['name' => 'Strength']);
    $program = ExerciseProgram::factory()->create([
        'name' => 'Strength',
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

    $slotDates = [
        '2026-05-09 08:00:00',
        '2026-05-11 14:00:00',
        '2026-05-12 11:00:00',
        '2026-05-15 08:00:00',
        '2026-05-18 11:00:00',
        '2026-05-22 14:00:00',
        '2026-05-25 08:00:00',
        '2026-05-29 11:00:00',
        '2026-06-01 14:00:00',
        '2026-06-05 08:00:00',
    ];

    $slots = collect($slotDates)->map(fn (string $dateTime) => TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse($dateTime),
    ]));

    $compiler = app(TrainingSessionCompiler::class);
    $firstCompiled = $compiler->compile($slots[0]->fresh());
    $thirdCompiled = $compiler->compile($slots[2]->fresh());

    $firstSets = collect(collect($firstCompiled->exercises)
        ->firstWhere('exerciseId', $exercise->id)
        ->sets);

    $firstWeights = $firstSets
        ->map(fn ($set) => (float) collect($set->values)->firstWhere('settingKey', 'weight')->plannedValue)
        ->values()
        ->all();

    $thirdSets = collect(collect($thirdCompiled->exercises)
        ->firstWhere('exerciseId', $exercise->id)
        ->sets);

    $thirdWeights = $thirdSets
        ->map(fn ($set) => (float) collect($set->values)->firstWhere('settingKey', 'weight')->plannedValue)
        ->values()
        ->all();

    expect($firstWeights)->toBe([44.0, 46.5, 49.0])
        ->and($thirdWeights)->toBe([44.0, 46.5, 49.0, 51.5]);
});

it('uses the active block baseline metric when compiling automatic weights', function () {
    $athlete = User::factory()->athlete()->create();
    $coach = User::factory()->coach()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $category = Tag::factory()->withScope('training_category')->create(['name' => 'Strength']);
    $program = ExerciseProgram::factory()->create([
        'name' => 'Strength',
        'exercise_category_id' => $category->id,
    ]);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exercise = Exercise::factory()->create([
        'name' => 'Front Squat',
        'config' => [
            'settings' => ['reps', 'weight'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 8, 'applyPer' => 'session'],
            'weight' => ['mode' => 'automatic', 'oneRepMaxModifier' => 100, 'applyPer' => 'session'],
            'preview' => ['weeks' => 5, 'sessionsPerWeek' => 1],
        ],
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
    ]);

    TrainingProgramBlock::create([
        'group_id' => $group->id,
        'user_id' => null,
        'category_id' => $category->id,
        'type' => TrainingProgramBlockTypeEnum::Category,
        'start' => '2026-04-27',
        'end' => '2026-05-31',
        'note' => 'Strength Block',
        'active' => true,
        'config' => ['goal' => 5, 'autoRecord1rm' => false],
    ]);

    $baselineMetric = MetricSubmission::query()->create([
        'user_id' => $athlete->id,
        'metric' => \App\Data\Athlete\Metric\MetricEnum::OneRepMax,
        'recorded_by' => $coach->id,
        'recorded_at' => '2026-04-20',
        'owner_type' => null,
        'owner_id' => null,
    ]);
    $baselineMetric->values()->createMany([
        ['field' => 'measuredReps', 'value' => '5'],
        ['field' => 'measuredWeight', 'value' => '88'],
        ['field' => 'estimated1RM', 'value' => '100'],
    ]);

    $laterMetric = MetricSubmission::query()->create([
        'user_id' => $athlete->id,
        'metric' => \App\Data\Athlete\Metric\MetricEnum::OneRepMax,
        'recorded_by' => $coach->id,
        'recorded_at' => '2026-04-28',
        'owner_type' => null,
        'owner_id' => null,
    ]);
    $laterMetric->values()->createMany([
        ['field' => 'measuredReps', 'value' => '5'],
        ['field' => 'measuredWeight', 'value' => '96'],
        ['field' => 'estimated1RM', 'value' => '109.1'],
    ]);

    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2026-04-28 09:00:00'),
    ]);

    $weightValue = $slot->fresh('exercises.sets.values')
        ->exercises->first()->sets->first()->values->firstWhere('setting_key', 'weight');

    expect((float) $weightValue?->planned_decimal_value)->toBe(74.0);
});

it('reuses cached slot timelines and metric lookups across repeated compiles in one rebuild run', function () {
    $athlete = User::factory()->athlete()->create();
    $coach = User::factory()->coach()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Conditioning']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps', 'rest'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 6, 'applyPer' => 'session'],
            'rest' => ['default' => 60, 'applyPer' => 'week'],
            'preview' => ['weeks' => 2, 'sessionsPerWeek' => 2],
        ],
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
    ]);

    $oneRepMax = MetricSubmission::query()->create([
        'user_id' => $athlete->id,
        'metric' => \App\Data\Athlete\Metric\MetricEnum::OneRepMax,
        'recorded_by' => $coach->id,
        'recorded_at' => '2026-04-20',
        'owner_type' => null,
        'owner_id' => null,
    ]);
    $oneRepMax->values()->createMany([
        ['field' => 'measuredReps', 'value' => '5'],
        ['field' => 'measuredWeight', 'value' => '88'],
        ['field' => 'estimated1RM', 'value' => '100'],
    ]);

    $heartRate = MetricSubmission::query()->create([
        'user_id' => $athlete->id,
        'metric' => \App\Data\Athlete\Metric\MetricEnum::HeartRate,
        'recorded_by' => $coach->id,
        'recorded_at' => '2026-04-20',
        'owner_type' => null,
        'owner_id' => null,
    ]);
    $heartRate->values()->createMany([
        ['field' => 'heartRate', 'value' => '190'],
        ['field' => 'anaerobicThreshold', 'value' => '90'],
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2026-04-28 09:00:00'),
    ]);

    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2026-04-30 09:00:00'),
    ])->fresh();

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $compiler = app(TrainingSessionCompiler::class);
    $compiler->compile($slot);
    $compiler->compile($slot);

    $slotTimelineQueries = collect($queries)
        ->filter(fn (string $sql) => str_contains($sql, 'training_program_slots'))
        ->count();
    $metricQueries = collect($queries)
        ->filter(fn (string $sql) => str_contains($sql, 'user_metric_submissions'))
        ->count();

    expect($slotTimelineQueries)->toBe(1)
        ->and($metricQueries)->toBe(2);
});

it('preserves program exercise pivot sort when the same exercise appears in multiple sections', function () {
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Strength']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exercise = Exercise::factory()->create([
        'name' => 'Front Squat',
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 8, 'applyPer' => 'session'],
        ],
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'type' => 'warm_up',
        'group' => 'C',
        'sort' => 0,
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'type' => 'main',
        'group' => 'A',
        'sort' => 0,
    ]);

    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2026-04-28 09:00:00'),
    ]);

    $slotExercises = $slot->fresh('exercises')
        ->exercises
        ->keyBy(fn ($slotExercise): string => $slotExercise->type.'-'.$slotExercise->group);

    expect($slotExercises['warm_up-C']->sort)->toBe(0)
        ->and($slotExercises['main-A']->sort)->toBe(0);
});

it('excludes automatic metric-dependent exercises when required athlete metrics are missing', function () {
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $program = ExerciseProgram::factory()->create(['name' => 'Metric Dependent']);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $autoWeightExercise = Exercise::factory()->create([
        'name' => 'Auto Weight',
        'config' => [
            'settings' => ['reps', 'weight'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 6, 'applyPer' => 'session'],
            'weight' => ['mode' => 'automatic', 'oneRepMaxModifier' => 100, 'applyPer' => 'session'],
        ],
    ]);

    $autoHeartRateExercise = Exercise::factory()->create([
        'name' => 'Auto Heart Rate',
        'config' => [
            'settings' => ['duration', 'heartRate', 'heartRateZone'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'duration' => ['unit' => 'seconds', 'default' => 60, 'applyPer' => 'session'],
            'heartRate' => ['mode' => 'automatic_jogging', 'applyPer' => 'session'],
            'heartRateZone' => ['default' => '2', 'applyPer' => 'session'],
        ],
    ]);

    $manualExercise = Exercise::factory()->create([
        'name' => 'Manual Reps',
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 8, 'applyPer' => 'session'],
        ],
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $autoWeightExercise->id,
        'sort' => 0,
    ]);
    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $autoHeartRateExercise->id,
        'sort' => 1,
    ]);
    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $manualExercise->id,
        'sort' => 2,
    ]);

    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2026-04-28 09:00:00'),
    ]);

    $exerciseNames = $slot->fresh('exercises.exercise')
        ->exercises
        ->pluck('exercise.name')
        ->all();

    expect($exerciseNames)->toBe(['Manual Reps']);
});
