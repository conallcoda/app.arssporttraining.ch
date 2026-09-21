<?php

use App\Data\Athlete\Metric\MetricEnum;
use App\Models\Athlete\MetricSubmission;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Tag;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramBlock;
use App\Models\Training\TrainingProgramBlockTypeEnum;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotExerciseStatusEnum;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('previews and selectively repairs regression-era automatic 1rm exercises', function () {
    Carbon::setTestNow('2026-09-01 08:00:00');

    $athlete = User::factory()->athlete()->create();
    $coach = User::factory()->coach()->create();
    $group = UserGroup::create(['name' => 'Repair group']);
    $category = Tag::factory()->withScope('training_category')->create(['name' => 'Strength']);
    $program = ExerciseProgram::factory()->create([
        'name' => 'Five sessions',
        'exercise_category_id' => $category->id,
        'config' => ['weeks' => 5],
    ]);
    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);

    $automaticExercise = Exercise::factory()->create([
        'name' => 'Back Squat',
        'config' => [
            'settings' => ['reps', 'weight'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 6, 'applyPer' => 'session'],
            'weight' => ['mode' => 'automatic', 'oneRepMaxModifier' => 100, 'applyPer' => 'session'],
            'preview' => ['groupingMode' => 'groups', 'groupSize' => 2],
        ],
    ]);
    $manualExercise = Exercise::factory()->create([
        'name' => 'Accessory',
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 10, 'applyPer' => 'session'],
            'preview' => ['groupingMode' => 'groups', 'groupSize' => 2],
        ],
    ]);
    $automaticPivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $automaticExercise->id,
        'sort' => 0,
    ]);
    $manualPivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $manualExercise->id,
        'sort' => 1,
    ]);

    TrainingProgramBlock::create([
        'group_id' => $group->id,
        'category_id' => $category->id,
        'type' => TrainingProgramBlockTypeEnum::Category,
        'start' => '2026-09-01',
        'end' => '2026-10-01',
        'note' => 'Five session block',
        'active' => true,
        'config' => ['goal' => 30, 'autoRecord1rm' => false],
    ]);

    $metric = MetricSubmission::query()->create([
        'user_id' => $athlete->id,
        'metric' => MetricEnum::OneRepMax,
        'recorded_by' => $coach->id,
        'recorded_at' => '2026-09-01',
    ]);
    $metric->values()->createMany([
        ['field' => 'measuredReps', 'value' => '1'],
        ['field' => 'measuredWeight', 'value' => '25'],
        ['field' => 'estimated1RM', 'value' => '25'],
    ]);

    $slots = collect(range(0, 4))->map(fn (int $index) => TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => Carbon::parse('2026-09-02 09:00:00')->addDays($index * 3),
    ]));

    $automaticRows = $slots->map(fn (TrainingProgramSlot $slot) => $slot
        ->fresh('exercises.sets.values')
        ->exercises
        ->firstWhere('exercise_program_exercise_id', $automaticPivot->id));
    $manualRowIds = $slots->mapWithKeys(fn (TrainingProgramSlot $slot): array => [
        $slot->id => $slot->fresh('exercises')->exercises
            ->firstWhere('exercise_program_exercise_id', $manualPivot->id)->id,
    ]);

    foreach ($automaticRows as $row) {
        $row->sets->first()->values->firstWhere('setting_key', 'weight')->update([
            'planned_decimal_value' => 1,
        ]);
    }
    $automaticRows->first()->update(['status' => TrainingProgramSlotExerciseStatusEnum::Completed]);

    $this->artisan('training:repair-automatic-1rm-session-counts', [
        '--training-program' => [$trainingProgram->id],
    ])->expectsOutputToContain('Preview only: 4 exercise sessions would be recompiled; 1 recorded sessions would be preserved.')
        ->assertSuccessful();

    expect((float) $automaticRows->last()->sets->first()->values
        ->firstWhere('setting_key', 'weight')->fresh()->planned_decimal_value)->toBe(1.0);

    $this->artisan('training:repair-automatic-1rm-session-counts', [
        '--training-program' => [$trainingProgram->id],
        '--apply' => true,
    ])->expectsOutputToContain('Recompiled 4 affected automatic-1RM exercise sessions.')
        ->expectsOutputToContain('Preserved 1 recorded exercise sessions')
        ->assertSuccessful();

    $recordedWeight = $slots->first()->fresh('exercises.sets.values')->exercises
        ->firstWhere('exercise_program_exercise_id', $automaticPivot->id)
        ->sets->first()->values->firstWhere('setting_key', 'weight');
    $repairedWeight = $slots->last()->fresh('exercises.sets.values')->exercises
        ->firstWhere('exercise_program_exercise_id', $automaticPivot->id)
        ->sets->first()->values->firstWhere('setting_key', 'weight');
    $manualRowIdsAfter = $slots->mapWithKeys(fn (TrainingProgramSlot $slot): array => [
        $slot->id => $slot->fresh('exercises')->exercises
            ->firstWhere('exercise_program_exercise_id', $manualPivot->id)->id,
    ]);

    expect((float) $recordedWeight->planned_decimal_value)->toBe(1.0)
        ->and((float) $repairedWeight->planned_decimal_value)->toBe(28.0)
        ->and($manualRowIdsAfter->all())->toBe($manualRowIds->all());

    Carbon::setTestNow();
});
