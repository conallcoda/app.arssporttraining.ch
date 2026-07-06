<?php

use App\Data\Athlete\Metric\MetricEnum;
use App\Data\Athlete\Metric\MetricSubmissionData;
use App\Data\Athlete\Metric\Metrics\OneRepMaxMetric;
use App\Data\Exercise\Preview\SessionGroupingConfig;
use App\Models\Athlete\MetricSubmission;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Tag;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramBlock;
use App\Models\Training\TrainingProgramBlockTypeEnum;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotStatusEnum;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Training\TrainingSessionMaterializer;
use App\Training\TrainingSessionRebuildService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('preloads compilation relations before materializing rebuilt future slots', function () {
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $program = ExerciseProgram::factory()->create();
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 6, 'applyPer' => 'session'],
        ],
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-10 09:00:00'),
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-12 09:00:00'),
    ]);

    $mock = Mockery::mock(TrainingSessionMaterializer::class);
    $mock->shouldReceive('materialize')
        ->twice()
        ->withArgs(function (TrainingProgramSlot $slot, bool $force): bool {
            return $force === true
                && $slot->relationLoaded('trainingProgram')
                && $slot->trainingProgram->relationLoaded('program')
                && $slot->trainingProgram->program->relationLoaded('exercises');
        });
    app()->instance(TrainingSessionMaterializer::class, $mock);

    app(TrainingSessionRebuildService::class)
        ->rebuildFutureSlotsForExerciseProgram($program->id);
});

it('can rebuild future exercise program slots from a specific date', function () {
    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $program = ExerciseProgram::factory()->create();
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 6, 'applyPer' => 'session'],
        ],
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-10 09:00:00'),
    ]);

    $includedSlot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-12 09:00:00'),
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-14 09:00:00'),
    ]);

    $mock = Mockery::mock(TrainingSessionMaterializer::class);
    $mock->shouldReceive('materialize')
        ->twice()
        ->withArgs(fn (TrainingProgramSlot $slot, bool $force): bool => $force === true
            && $slot->datetime->gte($includedSlot->datetime));
    app()->instance(TrainingSessionMaterializer::class, $mock);

    app(TrainingSessionRebuildService::class)
        ->rebuildFutureSlotsForExerciseProgram($program->id, '2030-04-12');
});

it('rebuilds open exercise program slots from a specific date including past unrecorded sessions', function () {
    Carbon::setTestNow('2030-04-13 12:00:00');

    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $program = ExerciseProgram::factory()->create();
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 6, 'applyPer' => 'session'],
        ],
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-10 09:00:00'),
    ]);

    $pastOpenSlot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-12 09:00:00'),
        'status' => TrainingProgramSlotStatusEnum::Pending,
    ]);

    TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-13 09:00:00'),
        'status' => TrainingProgramSlotStatusEnum::Completed,
        'completed_at' => Carbon::parse('2030-04-13 10:00:00'),
    ]);

    $futureOpenSlot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2030-04-14 09:00:00'),
        'status' => TrainingProgramSlotStatusEnum::Pending,
    ]);

    $seenIds = [];
    $mock = Mockery::mock(TrainingSessionMaterializer::class);
    $mock->shouldReceive('materialize')
        ->twice()
        ->withArgs(function (TrainingProgramSlot $slot, bool $force) use (&$seenIds): bool {
            $seenIds[] = $slot->id;

            return $force === true;
        });
    app()->instance(TrainingSessionMaterializer::class, $mock);

    app(TrainingSessionRebuildService::class)
        ->rebuildOpenSlotsForExerciseProgram($program->id, '2030-04-12');

    expect($seenIds)->toBe([$pastOpenSlot->id, $futureOpenSlot->id]);

    Carbon::setTestNow();
});

it('rebuilds future slots from the full plan timeline when grouping affects deloading', function () {
    Carbon::setTestNow('2030-04-12 12:00:00');

    $athlete = User::factory()->athlete()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $program = ExerciseProgram::factory()->create();
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $exercise = Exercise::factory()->create([
        'config' => [
            'settings' => [],
            'sets' => ['default' => 4, 'label' => 'Set', 'deload' => 'odd', 'deloadBy' => 1],
            'preview' => ['groupingMode' => 'none', 'groupSize' => 1],
        ],
    ]);

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $config = $program->config;
    $overrides = $config->defaultExerciseOverrides($pivot->id);
    $overrides->sessionGrouping = SessionGroupingConfig::from([
        'mode' => 'none',
        'groupSize' => 1,
        'copyValuesAutomatically' => false,
    ]);
    $config->setDefaultExerciseOverrides($pivot->id, $overrides);
    $program->config = $config;
    $program->save();

    foreach (['2030-04-01', '2030-04-08', '2030-04-15', '2030-04-22'] as $date) {
        TrainingProgramSlot::factory()->create([
            'training_program_id' => $trainingProgram->id,
            'user_id' => $athlete->id,
            'datetime' => Carbon::parse($date.' 09:00:00'),
        ]);
    }

    $futureSlot = TrainingProgramSlot::query()
        ->where('datetime', '2030-04-15 09:00:00')
        ->firstOrFail();

    app(TrainingSessionMaterializer::class)->materialize($futureSlot, force: true);

    expect($futureSlot->fresh('exercises.sets')->exercises->firstOrFail()->sets)->toHaveCount(3);

    $config = $program->fresh()->config;
    $overrides = $config->defaultExerciseOverrides($pivot->id);
    $overrides->sessionGrouping = SessionGroupingConfig::from([
        'mode' => 'groups',
        'groupSize' => 2,
        'copyValuesAutomatically' => true,
    ]);
    $config->setDefaultExerciseOverrides($pivot->id, $overrides);
    $program->config = $config;
    $program->save();

    app(TrainingSessionRebuildService::class)->rebuildFutureSlotsForExerciseProgram($program->id);

    expect($futureSlot->fresh('exercises.sets')->exercises->firstOrFail()->sets)->toHaveCount(4);
});

it('rebuilds all open athlete program slots after metric values are rewritten', function () {
    Carbon::setTestNow('2030-01-01 12:00:00');

    $athlete = User::factory()->athlete()->create();
    $coach = User::factory()->coach()->create();
    $group = UserGroup::create(['name' => 'Test Group']);
    $category = Tag::factory()->withScope('training_category')->create(['name' => 'Strength']);

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
                'applyPer' => 'per_set',
            ],
            'weight' => [
                'mode' => 'automatic',
                'oneRepMaxModifier' => 85,
                'default' => 5,
                'applyPer' => 'per_set',
            ],
            'tempo' => ['default' => '3010', 'applyPer' => 'week'],
            'rest' => ['default' => 60, 'applyPer' => 'week'],
            'preview' => ['weeks' => 5, 'sessionsPerWeek' => 1, 'groupingMode' => 'none', 'groupSize' => 1],
        ],
    ]);

    $programA = ExerciseProgram::factory()->create([
        'name' => 'Strength A',
        'exercise_category_id' => $category->id,
    ]);
    $programB = ExerciseProgram::factory()->create([
        'name' => 'Strength B',
        'exercise_category_id' => $category->id,
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $programA->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);
    ExerciseProgramExercise::create([
        'exercise_program_id' => $programB->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $trainingProgramA = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $programA->id,
    ]);
    $trainingProgramB = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $programB->id,
    ]);

    TrainingProgramBlock::create([
        'group_id' => $group->id,
        'user_id' => $athlete->id,
        'category_id' => $category->id,
        'type' => TrainingProgramBlockTypeEnum::Category,
        'start' => '2030-02-01',
        'end' => '2030-03-08',
        'note' => 'Strength Block',
        'active' => true,
        'config' => ['goal' => 10, 'autoRecord1rm' => true],
    ]);

    $metric = MetricSubmission::create([
        'user_id' => $athlete->id,
        'metric' => MetricEnum::OneRepMax,
        'recorded_by' => $coach->id,
        'recorded_at' => '2030-01-15',
        'owner_type' => User::class,
        'owner_id' => $coach->id,
    ]);
    $metric->values()->createMany([
        ['field' => 'measuredReps', 'value' => '1'],
        ['field' => 'measuredWeight', 'value' => '20'],
        ['field' => 'estimated1RM', 'value' => '20'],
    ]);

    $slotA = null;
    $slotB = null;

    foreach (['2030-02-05', '2030-02-12', '2030-02-19', '2030-02-26', '2030-03-05'] as $date) {
        $slot = TrainingProgramSlot::factory()->create([
            'training_program_id' => $trainingProgramA->id,
            'user_id' => $athlete->id,
            'datetime' => Carbon::parse($date.' 09:00:00'),
        ]);

        $slotA ??= $slot;
    }

    foreach (['2030-02-07', '2030-02-14', '2030-02-21', '2030-02-28', '2030-03-07'] as $date) {
        $slot = TrainingProgramSlot::factory()->create([
            'training_program_id' => $trainingProgramB->id,
            'user_id' => $athlete->id,
            'datetime' => Carbon::parse($date.' 09:00:00'),
        ]);

        $slotB ??= $slot;
    }

    $beforeA = slotWeights($slotA);
    $beforeB = slotWeights($slotB);

    (new MetricSubmissionData(
        id: $metric->id,
        user_id: $athlete->id,
        metric: MetricEnum::OneRepMax,
        recorded_by: $coach->id,
        recorded_at: '2030-01-15',
        data: new OneRepMaxMetric(measuredReps: 1, measuredWeight: 47),
    ))->persist();

    $afterA = slotWeights($slotA);
    $afterB = slotWeights($slotB);

    expect($afterA)->toBe($afterB)
        ->and($afterA)->not->toBe($beforeA)
        ->and($afterB)->not->toBe($beforeB)
        ->and($afterA[0])->toBeGreaterThan($beforeA[0])
        ->and($afterB[0])->toBeGreaterThan($beforeB[0]);

    Carbon::setTestNow();
});

function slotWeights(TrainingProgramSlot $slot): array
{
    return $slot
        ->fresh('exercises.sets.values')
        ->exercises
        ->firstOrFail()
        ->sets
        ->sortBy('set_number')
        ->map(fn ($set): float => (float) $set->values->firstWhere('setting_key', 'weight')->planned_decimal_value)
        ->values()
        ->all();
}
