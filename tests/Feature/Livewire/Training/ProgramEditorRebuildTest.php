<?php

use App\Livewire\Training\View\ProgramEditor;
use App\Data\Exercise\Settings\RepsSetting;
use App\Data\Training\Config\ExerciseOverrides;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Training\TrainingSessionRebuildDispatcher;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('dispatches a single shared rebuild when reordering section exercises', function () {
    $firstExercise = Exercise::factory()->create();
    $secondExercise = Exercise::factory()->create();
    $program = ExerciseProgram::factory()->create();

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $firstExercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $secondExercise->id,
        'sort' => 1,
        'type' => 'main',
    ]);

    $mock = Mockery::mock(TrainingSessionRebuildDispatcher::class);
    $mock->shouldReceive('dispatchFutureSlotsForExerciseProgramChange')
        ->once()
        ->with($program->id);
    $mock->shouldNotReceive('dispatchFutureSlotsForExerciseProgram');
    $mock->shouldNotReceive('dispatchFutureSlotsForAthleteExerciseProgram');
    app()->instance(TrainingSessionRebuildDispatcher::class, $mock);

    Livewire::test(ProgramEditor::class, [
        'exerciseProgram' => $program,
        'planId' => $program->id,
    ])->call('moveRelationshipItem', 'section_exercises', 1, -1);
});

it('persists drag-and-drop reordering for section exercises', function () {
    $firstExercise = Exercise::factory()->create();
    $secondExercise = Exercise::factory()->create();
    $program = ExerciseProgram::factory()->create();

    $firstPivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $firstExercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $secondPivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $secondExercise->id,
        'sort' => 1,
        'type' => 'main',
    ]);

    $mock = Mockery::mock(TrainingSessionRebuildDispatcher::class);
    $mock->shouldReceive('dispatchFutureSlotsForExerciseProgramChange')
        ->once()
        ->with($program->id);
    app()->instance(TrainingSessionRebuildDispatcher::class, $mock);

    Livewire::test(ProgramEditor::class, [
        'exerciseProgram' => $program,
        'planId' => $program->id,
    ])->call('reorderRelationshipItem', 'section_exercises', 1, 0);

    expect(ExerciseProgramExercise::query()->findOrFail($firstPivot->id)->sort)->toBe(1);
    expect(ExerciseProgramExercise::query()->findOrFail($secondPivot->id)->sort)->toBe(0);
});

it('dispatches a single shared rebuild when importing a section', function () {
    $targetExercise = Exercise::factory()->create();
    $sourceExerciseOne = Exercise::factory()->create();
    $sourceExerciseTwo = Exercise::factory()->create();

    $targetProgram = ExerciseProgram::factory()->create();
    $sourceProgram = ExerciseProgram::factory()->create();

    ExerciseProgramExercise::create([
        'exercise_program_id' => $targetProgram->id,
        'exercise_id' => $targetExercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $sourceProgram->id,
        'exercise_id' => $sourceExerciseOne->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $sourceProgram->id,
        'exercise_id' => $sourceExerciseTwo->id,
        'sort' => 1,
        'type' => 'main',
    ]);

    $mock = Mockery::mock(TrainingSessionRebuildDispatcher::class);
    $mock->shouldReceive('dispatchFutureSlotsForExerciseProgramChange')
        ->once()
        ->with($targetProgram->id);
    $mock->shouldNotReceive('dispatchFutureSlotsForExerciseProgram');
    $mock->shouldNotReceive('dispatchFutureSlotsForAthleteExerciseProgram');
    app()->instance(TrainingSessionRebuildDispatcher::class, $mock);

    Livewire::test(ProgramEditor::class, [
        'exerciseProgram' => $targetProgram,
        'planId' => $targetProgram->id,
    ])
        ->set('importProgramId', $sourceProgram->id)
        ->call('confirmImportSectionExercises');
});

it('dispatches a shared rebuild when changing an exercise from the dropdown', function () {
    $originalExercise = Exercise::factory()->create();
    $replacementExercise = Exercise::factory()->create();
    $program = ExerciseProgram::factory()->create();

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $originalExercise->id,
        'sort' => 0,
        'group' => 'A',
        'type' => 'main',
    ]);

    $mock = Mockery::mock(TrainingSessionRebuildDispatcher::class);
    $mock->shouldReceive('dispatchFutureSlotsForExerciseProgramChange')
        ->once()
        ->with($program->id);
    app()->instance(TrainingSessionRebuildDispatcher::class, $mock);

    Livewire::test(ProgramEditor::class, [
        'exerciseProgram' => $program,
        'planId' => $program->id,
    ])->set('data.section_exercises', [[
        'id' => $replacementExercise->id,
        'program_exercise_id' => $pivot->id,
        '_key' => 'item_1',
        'sort' => 0,
        'group' => 'A',
    ]]);
});

it('dispatches a shared rebuild when changing an exercise group from the dropdown', function () {
    $exercise = Exercise::factory()->create();
    $program = ExerciseProgram::factory()->create();

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'group' => null,
        'type' => 'main',
    ]);

    $mock = Mockery::mock(TrainingSessionRebuildDispatcher::class);
    $mock->shouldReceive('dispatchFutureSlotsForExerciseProgramChange')
        ->once()
        ->with($program->id);
    app()->instance(TrainingSessionRebuildDispatcher::class, $mock);

    Livewire::test(ProgramEditor::class, [
        'exerciseProgram' => $program,
        'planId' => $program->id,
    ])->set('data.section_exercises', [[
        'id' => $exercise->id,
        'program_exercise_id' => $pivot->id,
        '_key' => 'item_1',
        'sort' => 0,
        'group' => 'B',
    ]]);
});

it('hydrates persisted section exercise groups into the selector client state and opens on the selected tab', function () {
    $exercise = Exercise::factory()->create();
    $program = ExerciseProgram::factory()->create();

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'group' => 'B',
        'type' => 'main',
    ]);

    $component = Livewire::test(ProgramEditor::class, [
        'exerciseProgram' => $program,
        'planId' => $program->id,
    ]);

    $state = $component->instance()->relationshipSelectorClientInitialState('section_exercises', 40);

    expect($state['initialListKey'])->toBe('selected')
        ->and($state['selectedItems'])->toHaveCount(1)
        ->and($state['selectedItems'][0]['item']['group'])->toBe('B');
});

it('defaults the selector client state to the first list tab when no exercises are selected', function () {
    $program = ExerciseProgram::factory()->create();

    $component = Livewire::test(ProgramEditor::class, [
        'exerciseProgram' => $program,
        'planId' => $program->id,
    ]);

    $state = $component->instance()->relationshipSelectorClientInitialState('section_exercises', 40);

    expect($state['initialListKey'])->toBe('exercises')
        ->and($state['selectedItems'])->toBe([]);
});

it('switches to the selected tab after importing exercises from a program in the selector', function () {
    $targetProgram = ExerciseProgram::factory()->create();
    $sourceProgram = ExerciseProgram::factory()->create();
    $exercise = Exercise::factory()->create();

    ExerciseProgramExercise::create([
        'exercise_program_id' => $sourceProgram->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $component = Livewire::test(ProgramEditor::class, [
        'exerciseProgram' => $targetProgram,
        'planId' => $targetProgram->id,
    ]);

    $response = $component->instance()->importExercisesFromProgramSelector(
        'section_exercises',
        'programs',
        ['key' => $sourceProgram->id],
        [],
    );

    expect($response['activeListKey'] ?? null)->toBe('selected')
        ->and($response['selectedItems'] ?? [])->toHaveCount(1);
});

it('marks empty source programs so the selector can hide their import button', function () {
    $targetProgram = ExerciseProgram::factory()->create();
    $emptyProgram = ExerciseProgram::factory()->create();

    $component = Livewire::test(ProgramEditor::class, [
        'exerciseProgram' => $targetProgram,
        'planId' => $targetProgram->id,
    ]);

    $page = $component->instance()->loadExerciseSelectorPrograms('section_exercises', 'programs');
    $emptyRecord = collect($page['records'] ?? [])->firstWhere('key', $emptyProgram->id);

    expect($emptyRecord)->not->toBeNull()
        ->and(data_get($emptyRecord, 'selector_program_has_exercises'))->toBeFalse();
});

it('does not render the legacy import controls in the program editor', function () {
    $program = ExerciseProgram::factory()->create();

    Livewire::test(ProgramEditor::class, [
        'exerciseProgram' => $program,
        'planId' => $program->id,
    ])->assertDontSee('Import Exercises From');
});

it('preserves historical exercises and gates new ones to the first future session', function () {
    Carbon::setTestNow('2026-05-15 12:00:00');

    $historicalExercise = Exercise::factory()->create();
    $futureExercise = Exercise::factory()->create();
    $program = ExerciseProgram::factory()->create();

    $historicalPivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $historicalExercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $mock = Mockery::mock(TrainingSessionRebuildDispatcher::class);
    $mock->shouldReceive('dispatchFutureSlotsForExerciseProgramChange')
        ->once()
        ->with($program->id);
    app()->instance(TrainingSessionRebuildDispatcher::class, $mock);

    Livewire::test(ProgramEditor::class, [
        'exerciseProgram' => $program,
        'planId' => $program->id,
        'lockedSessionsByWeek' => [[true, false]],
        'weekSessionDates' => [['2026-05-10', '2026-05-20']],
    ])->set('data.section_exercises', [[
        'id' => $futureExercise->id,
        '_key' => 'future_1',
        'sort' => 0,
    ]]);

    $rows = ExerciseProgramExercise::query()
        ->where('exercise_program_id', $program->id)
        ->where('type', 'main')
        ->orderBy('sort')
        ->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('exercise_id')->all())->toContain($historicalExercise->id, $futureExercise->id)
        ->and($rows->firstWhere('id', $historicalPivot->id)?->exercise_id)->toBe($historicalExercise->id);

    $newPivotId = (int) $rows->firstWhere('exercise_id', $futureExercise->id)?->id;

    expect($program->fresh()->config->defaultExerciseOverrides($newPivotId)->startsAtDate)->toBe('2026-05-20');
});

it('routes section imports through the same historical guard and copies source overrides forward', function () {
    Carbon::setTestNow('2026-05-15 12:00:00');

    $currentExercise = Exercise::factory()->create();
    $sourceExercise = Exercise::factory()->create([
        'config' => [
            'settings' => ['reps'],
            'sets' => ['default' => 1, 'label' => 'Set', 'deload' => 'none'],
            'reps' => ['mode' => 'manual', 'default' => 5, 'applyPer' => 'session'],
        ],
    ]);

    $targetProgram = ExerciseProgram::factory()->create();
    $sourceProgram = ExerciseProgram::factory()->create();

    $currentPivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $targetProgram->id,
        'exercise_id' => $currentExercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $sourcePivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $sourceProgram->id,
        'exercise_id' => $sourceExercise->id,
        'sort' => 0,
        'group' => 'B',
        'type' => 'main',
    ]);

    $sourceConfig = $sourceProgram->config;
    $sourceConfig->setDefaultExerciseOverrides($sourcePivot->id, ExerciseOverrides::from([
        'reps' => RepsSetting::from(['mode' => 'manual', 'default' => 8, 'applyPer' => 'session']),
    ]));
    $sourceProgram->config = $sourceConfig;
    $sourceProgram->save();

    $mock = Mockery::mock(TrainingSessionRebuildDispatcher::class);
    $mock->shouldReceive('dispatchFutureSlotsForExerciseProgramChange')
        ->once()
        ->with($targetProgram->id);
    app()->instance(TrainingSessionRebuildDispatcher::class, $mock);

    Livewire::test(ProgramEditor::class, [
        'exerciseProgram' => $targetProgram,
        'planId' => $targetProgram->id,
        'lockedSessionsByWeek' => [[true, false]],
        'weekSessionDates' => [['2026-05-10', '2026-05-20']],
    ])
        ->set('importProgramId', $sourceProgram->id)
        ->call('confirmImportSectionExercises');

    $rows = ExerciseProgramExercise::query()
        ->where('exercise_program_id', $targetProgram->id)
        ->where('type', 'main')
        ->orderBy('sort')
        ->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('exercise_id')->all())->toContain($currentExercise->id, $sourceExercise->id)
        ->and($rows->firstWhere('id', $currentPivot->id)?->exercise_id)->toBe($currentExercise->id);

    $importedPivot = $rows->firstWhere('exercise_id', $sourceExercise->id);
    $importedOverrides = $targetProgram->fresh()->config->defaultExerciseOverrides((int) $importedPivot?->id);

    expect($importedOverrides->startsAtDate)->toBe('2026-05-20')
        ->and($importedOverrides->reps?->default)->toBe(8);
});

it('shows the same warning toast when a direct removal is blocked by immutable history', function () {
    Carbon::setTestNow('2026-05-15 12:00:00');

    $historicalExercise = Exercise::factory()->create();
    $program = ExerciseProgram::factory()->create();

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $historicalExercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $mock = Mockery::mock(TrainingSessionRebuildDispatcher::class);
    $mock->shouldNotReceive('dispatchFutureSlotsForExerciseProgramChange');
    app()->instance(TrainingSessionRebuildDispatcher::class, $mock);

    Livewire::test(ProgramEditor::class, [
        'exerciseProgram' => $program,
        'planId' => $program->id,
        'lockedSessionsByWeek' => [[true, false]],
        'weekSessionDates' => [['2026-05-10', '2026-05-20']],
    ])
        ->call('removeRelationshipSelectorItem', 'section_exercises', 0)
        ->assertDispatched('toast-show', function ($event, $params) {
            return ($params['slots']['text'] ?? null) === 'Some historical exercises were kept because past sessions can no longer be changed.'
                && ($params['dataset']['variant'] ?? null) === 'warning';
        });

    expect(ExerciseProgramExercise::query()
        ->where('exercise_program_id', $program->id)
        ->where('type', 'main')
        ->count())->toBe(1);
});
