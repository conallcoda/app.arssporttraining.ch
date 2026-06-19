<?php

use App\Livewire\Training\View\ProgramEditor;
use App\Data\Exercise\Settings\RepsSetting;
use App\Data\Training\Config\ExerciseOverrides;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Exercise\ExerciseProgramTypeEnum;
use App\Models\Tag;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
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

it('refreshes cached selector previews after section exercise edits', function () {
    $originalExercise = Exercise::factory()->create(['name' => 'Original']);
    $replacementExercise = Exercise::factory()->create(['name' => 'Replacement']);
    $newExercise = Exercise::factory()->create(['name' => 'Added']);
    $program = ExerciseProgram::factory()->create();

    $pivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $originalExercise->id,
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
    ])->call('applyRelationshipSelectorClientState', 'section_exercises', [
        [
            'id' => $replacementExercise->id,
            'program_exercise_id' => $pivot->id,
            '_key' => 'item_existing',
            'sort' => 0,
            'group' => null,
        ],
        [
            'id' => $newExercise->id,
            '_key' => 'item_new',
            'sort' => 0,
            'group' => 'A',
        ],
    ]);

    expect($program->fresh()->selector_preview_exercises)->toBe([
        'Replacement',
        'Added',
    ])->and($program->fresh()->selector_preview_exercise_count)->toBe(2);
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

it('marks immutable selected exercises as non-removable in selector client state', function () {
    Carbon::setTestNow('2026-05-15 12:00:00');

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
        'lockedSessionsByWeek' => [[true, false]],
        'weekSessionDates' => [['2026-05-10', '2026-05-20']],
    ]);

    $state = $component->instance()->relationshipSelectorClientInitialState('section_exercises', 40);

    expect($state['selectedItems'])->toHaveCount(1)
        ->and($state['selectedItems'][0]['item']['_remove_disabled'] ?? false)->toBeTrue()
        ->and($state['selectedItems'][0]['item']['_remove_disabled_label'] ?? null)->toBe('Recorded sessions keep this exercise in the plan.');
});

it('orders section exercises by group before sort order', function () {
    $exerciseA2 = Exercise::factory()->create();
    $exerciseB1 = Exercise::factory()->create();
    $exerciseA1 = Exercise::factory()->create();
    $exerciseUngrouped = Exercise::factory()->create();
    $program = ExerciseProgram::factory()->create();

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exerciseA2->id,
        'sort' => 1,
        'group' => 'A',
        'type' => 'main',
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exerciseB1->id,
        'sort' => 0,
        'group' => 'B',
        'type' => 'main',
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exerciseA1->id,
        'sort' => 0,
        'group' => 'A',
        'type' => 'main',
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exerciseUngrouped->id,
        'sort' => 0,
        'group' => null,
        'type' => 'main',
    ]);

    $component = Livewire::test(ProgramEditor::class, [
        'exerciseProgram' => $program,
        'planId' => $program->id,
    ]);

    expect(collect($component->get('data.section_exercises'))->map(fn (array $row) => [
        'id' => $row['id'],
        'group' => $row['group'],
        'sort' => $row['sort'],
    ])->all())->toBe([
        ['id' => $exerciseUngrouped->id, 'group' => null, 'sort' => 0],
        ['id' => $exerciseA1->id, 'group' => 'A', 'sort' => 0],
        ['id' => $exerciseA2->id, 'group' => 'A', 'sort' => 1],
        ['id' => $exerciseB1->id, 'group' => 'B', 'sort' => 0],
    ]);
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

it('imports main source exercises into the active warm-up section from the selector', function () {
    $targetProgram = ExerciseProgram::factory()->create();
    $sourceProgram = ExerciseProgram::factory()->create([
        'type' => ExerciseProgramTypeEnum::Program,
    ]);
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
    ])->set('activeSection', 'warm_up');

    $response = $component->instance()->importExercisesFromProgramSelector(
        'section_exercises',
        'programs',
        ['key' => $sourceProgram->id],
        [],
    );

    expect($response['activeListKey'] ?? null)->toBe('selected')
        ->and($response['selectedItems'] ?? [])->toHaveCount(1)
        ->and(data_get($response, 'selectedItems.0.item.id'))->toBe($exercise->id);
});

it('does not move exercises across group boundaries in the program editor', function () {
    $exerciseA = Exercise::factory()->create();
    $exerciseB = Exercise::factory()->create();
    $program = ExerciseProgram::factory()->create();

    $pivotA = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exerciseA->id,
        'sort' => 0,
        'group' => 'A',
        'type' => 'main',
    ]);

    $pivotB = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exerciseB->id,
        'sort' => 0,
        'group' => 'B',
        'type' => 'main',
    ]);

    $component = Livewire::test(ProgramEditor::class, [
        'exerciseProgram' => $program,
        'planId' => $program->id,
    ]);

    $component->call('moveRelationshipItem', 'section_exercises', 1, -1);

    expect(collect($component->get('data.section_exercises'))->pluck('id')->all())->toBe([$exerciseA->id, $exerciseB->id])
        ->and(ExerciseProgramExercise::query()->findOrFail($pivotA->id)->sort)->toBe(0)
        ->and(ExerciseProgramExercise::query()->findOrFail($pivotB->id)->sort)->toBe(0);
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

it('searches selector programs by name, category, and internal tags', function () {
    $targetProgram = ExerciseProgram::factory()->create();

    $ski = Tag::factory()->create([
        'scope' => 'exercise_category',
        'name' => 'Ski',
        'short_name' => 'SKI',
        'color' => 'blue',
    ]);

    $internalTag = Tag::factory()->create([
        'scope' => 'program_internal',
        'name' => 'Travel Block',
    ]);

    $matchingProgram = ExerciseProgram::factory()->create([
        'name' => 'Giant Slalom',
        'exercise_category_id' => $ski->id,
        'type' => 'program',
    ]);

    $matchingProgram->internalTags()->sync([$internalTag->id]);

    $otherProgram = ExerciseProgram::factory()->create([
        'name' => 'Back Squat Builder',
        'type' => 'program',
    ]);

    $component = Livewire::test(ProgramEditor::class, [
        'exerciseProgram' => $targetProgram,
        'planId' => $targetProgram->id,
    ]);

    $namePage = $component->instance()->loadExerciseSelectorPrograms('section_exercises', 'programs', 'Slalom');
    $categoryPage = $component->instance()->loadExerciseSelectorPrograms('section_exercises', 'programs', 'Ski');
    $tagPage = $component->instance()->loadExerciseSelectorPrograms('section_exercises', 'programs', 'Travel Block');

    expect(collect($namePage['records'] ?? [])->pluck('key')->all())->toContain($matchingProgram->id)
        ->and(collect($categoryPage['records'] ?? [])->pluck('key')->all())->toContain($matchingProgram->id)
        ->and(collect($tagPage['records'] ?? [])->pluck('key')->all())->toContain($matchingProgram->id)
        ->and(collect($namePage['records'] ?? [])->pluck('key')->all())->not->toContain($otherProgram->id);
});

it('shows warm-up, main, and cool-down source programs in the selector', function () {
    $targetProgram = ExerciseProgram::factory()->create([
        'type' => 'program',
    ]);

    $warmUpProgram = ExerciseProgram::factory()->create([
        'name' => 'Mobility Lower Body',
        'type' => 'warm_up',
    ]);

    $mainProgram = ExerciseProgram::factory()->create([
        'name' => 'Strength Builder',
        'type' => 'program',
    ]);

    $coolDownProgram = ExerciseProgram::factory()->create([
        'name' => 'Recovery Reset',
        'type' => 'warm_down',
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $warmUpProgram->id,
        'exercise_id' => Exercise::factory()->create()->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $mainProgram->id,
        'exercise_id' => Exercise::factory()->create()->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $coolDownProgram->id,
        'exercise_id' => Exercise::factory()->create()->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $component = Livewire::test(ProgramEditor::class, [
        'exerciseProgram' => $targetProgram,
        'planId' => $targetProgram->id,
    ]);

    $mobilityPage = $component->instance()->loadExerciseSelectorPrograms('section_exercises', 'programs', 'Mobility');
    $fullPage = $component->instance()->loadExerciseSelectorPrograms('section_exercises', 'programs');

    expect(collect($mobilityPage['records'] ?? [])->pluck('key')->all())->toContain($warmUpProgram->id)
        ->and(collect($fullPage['records'] ?? [])->pluck('key')->all())->toContain($warmUpProgram->id, $mainProgram->id, $coolDownProgram->id);
});

it('shows coach-owned programs in the selector', function () {
    $targetProgram = ExerciseProgram::factory()->create([
        'type' => 'program',
    ]);
    $owner = User::factory()->create();

    $ownedProgram = ExerciseProgram::factory()->create([
        'name' => 'Mobility Lower Body',
        'type' => 'warm_up',
        'owner_id' => $owner->id,
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $ownedProgram->id,
        'exercise_id' => Exercise::factory()->create()->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $component = Livewire::test(ProgramEditor::class, [
        'exerciseProgram' => $targetProgram,
        'planId' => $targetProgram->id,
    ]);

    $page = $component->instance()->loadExerciseSelectorPrograms('section_exercises', 'programs', 'Mobility');

    expect(collect($page['records'] ?? [])->pluck('key')->all())->toContain($ownedProgram->id);
});

it('serializes program-tab exercise names as badges in the selector', function () {
    $targetProgram = ExerciseProgram::factory()->create();
    $sourceProgram = ExerciseProgram::factory()->create([
        'name' => 'Giant Slalom',
        'type' => 'program',
    ]);

    $exerciseA = Exercise::factory()->create(['name' => 'Skiboots']);
    $exerciseB = Exercise::factory()->create(['name' => 'Hand on hip']);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $sourceProgram->id,
        'exercise_id' => $exerciseA->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $sourceProgram->id,
        'exercise_id' => $exerciseB->id,
        'sort' => 1,
        'type' => 'main',
    ]);

    $component = Livewire::test(ProgramEditor::class, [
        'exerciseProgram' => $targetProgram,
        'planId' => $targetProgram->id,
    ]);

    $page = $component->instance()->loadExerciseSelectorPrograms('section_exercises', 'programs');
    $record = collect($page['records'] ?? [])->firstWhere('key', $sourceProgram->id);
    $badges = data_get($record, 'views.programs.columns.0.badges', []);

    expect(collect($badges)->pluck('label')->all())->toContain('Skiboots', 'Hand on hip');
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
            return ($params['slots']['text'] ?? null) === 'Some historical exercises were kept because recorded past sessions can no longer be changed.'
                && ($params['dataset']['variant'] ?? null) === 'warning';
        });

    expect(ExerciseProgramExercise::query()
        ->where('exercise_program_id', $program->id)
        ->where('type', 'main')
        ->count())->toBe(1);
});

it('does not reorder or regroup exercises when removing an immutable exercise is blocked', function () {
    Carbon::setTestNow('2026-05-15 12:00:00');

    $protectedExercise = Exercise::factory()->create();
    $otherExercise = Exercise::factory()->create();
    $program = ExerciseProgram::factory()->create();

    $protectedPivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $protectedExercise->id,
        'sort' => 0,
        'group' => 'A',
        'type' => 'main',
    ]);

    $otherPivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $otherExercise->id,
        'sort' => 0,
        'group' => 'B',
        'type' => 'main',
    ]);

    $mock = Mockery::mock(TrainingSessionRebuildDispatcher::class);
    $mock->shouldNotReceive('dispatchFutureSlotsForExerciseProgramChange');
    app()->instance(TrainingSessionRebuildDispatcher::class, $mock);

    $component = Livewire::test(ProgramEditor::class, [
        'exerciseProgram' => $program,
        'planId' => $program->id,
        'lockedSessionsByWeek' => [[true, false]],
        'weekSessionDates' => [['2026-05-10', '2026-05-20']],
    ])
        ->call('removeRelationshipSelectorItem', 'section_exercises', 0)
        ->assertDispatched('toast-show', function ($event, $params) {
            return ($params['slots']['text'] ?? null) === 'Some historical exercises were kept because recorded past sessions can no longer be changed.'
                && ($params['dataset']['variant'] ?? null) === 'warning';
        });

    $protectedPivot->refresh();
    $otherPivot->refresh();

    expect($protectedPivot->sort)->toBe(0)
        ->and($protectedPivot->group)->toBe('A')
        ->and($otherPivot->sort)->toBe(0)
        ->and($otherPivot->group)->toBe('B')
        ->and($component->get('data.section_exercises'))->toHaveCount(2);
});

it('deletes unused removed program exercises but keeps pivots referenced by materialized slots', function () {
    $referencedExercise = Exercise::factory()->create();
    $unusedExercise = Exercise::factory()->create();
    $program = ExerciseProgram::factory()->create();
    $group = UserGroup::create(['name' => 'Team Alpha']);
    $athlete = User::factory()->athlete()->create();

    $referencedPivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $referencedExercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);
    $unusedPivot = ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $unusedExercise->id,
        'sort' => 1,
        'type' => 'main',
    ]);

    $trainingProgram = TrainingProgram::factory()->create([
        'group_id' => $group->id,
        'exercise_program_id' => $program->id,
    ]);
    $slot = TrainingProgramSlot::factory()->create([
        'training_program_id' => $trainingProgram->id,
        'user_id' => $athlete->id,
        'datetime' => '2026-05-10 09:00:00',
    ]);
    $slot->exercises()
        ->where('exercise_program_exercise_id', $unusedPivot->id)
        ->delete();
    TrainingProgramSlotExercise::query()->create([
        'training_program_slot_id' => $slot->id,
        'exercise_id' => $referencedExercise->id,
        'exercise_program_exercise_id' => $referencedPivot->id,
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
    ])
        ->set('data.section_exercises', [])
        ->call('saveSectionExercises')
        ->assertDispatched('toast-show', function ($event, $params) {
            return ($params['slots']['text'] ?? null) === 'Some historical exercises were kept because recorded past sessions can no longer be changed.'
                && ($params['dataset']['variant'] ?? null) === 'warning';
        });

    expect(ExerciseProgramExercise::query()->whereKey($referencedPivot->id)->exists())->toBeTrue()
        ->and(ExerciseProgramExercise::query()->whereKey($unusedPivot->id)->exists())->toBeFalse();
});
