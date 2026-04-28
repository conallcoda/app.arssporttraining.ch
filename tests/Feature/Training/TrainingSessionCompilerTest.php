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

    expect((float) $weightValue?->planned_decimal_value)->toBe(64.0);
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
