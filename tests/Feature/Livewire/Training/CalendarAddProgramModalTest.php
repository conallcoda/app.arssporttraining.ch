<?php

use App\Livewire\Training\CalendarAddProgramModal;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Exercise\ExerciseProgramTypeEnum;
use App\Models\Tag;
use App\Models\Training\TrainingProgram;
use App\Models\Users\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('imports an existing program from the selector programs tab', function () {
    $group = UserGroup::create(['name' => 'Ski Team']);
    $program = ExerciseProgram::factory()->create([
        'name' => 'Mobility Lower Body',
        'type' => ExerciseProgramTypeEnum::Program,
    ]);

    Livewire::test(CalendarAddProgramModal::class, [
        'groupId' => $group->id,
        'userId' => null,
    ])
        ->set('data.calendar_program_selection', [
            ['_key' => 'stale-a', 'id' => 11, 'sort' => 0, 'group' => null],
            ['_key' => 'stale-b', 'id' => 12, 'sort' => 1, 'group' => 'A'],
        ])
        ->call('addCalendarProgramFromSelector', 'calendar_program_selection', 'programs', [
            'key' => (string) $program->id,
        ])
        ->assertSet('data.calendar_program_selection', [])
        ->assertDispatched('programs-changed');

    $trainingProgram = TrainingProgram::query()->sole();
    $clonedProgram = ExerciseProgram::findOrFail($trainingProgram->exercise_program_id);

    expect($trainingProgram->group_id)->toBe($group->id)
        ->and($clonedProgram->id)->not->toBe($program->id)
        ->and($clonedProgram->name)->toBe('Mobility Lower Body')
        ->and($clonedProgram->parent_type)->toBe(TrainingProgram::class)
        ->and($clonedProgram->parent_id)->toBe($trainingProgram->id);
});

it('shows warm-up, main, and cool-down source programs in the add program selector', function () {
    $group = UserGroup::create(['name' => 'Ski Team']);

    $warmUpProgram = ExerciseProgram::factory()->create([
        'name' => 'Mobility Lower Body',
        'type' => ExerciseProgramTypeEnum::WarmUp,
    ]);

    $mainProgram = ExerciseProgram::factory()->create([
        'name' => 'Strength Builder',
        'type' => ExerciseProgramTypeEnum::Program,
    ]);

    $coolDownProgram = ExerciseProgram::factory()->create([
        'name' => 'Recovery Reset',
        'type' => ExerciseProgramTypeEnum::WarmDown,
    ]);

    foreach ([$warmUpProgram, $mainProgram, $coolDownProgram] as $program) {
        ExerciseProgramExercise::create([
            'exercise_program_id' => $program->id,
            'exercise_id' => Exercise::factory()->create()->id,
            'sort' => 0,
            'type' => 'main',
        ]);
    }

    $component = Livewire::test(CalendarAddProgramModal::class, [
        'groupId' => $group->id,
        'userId' => null,
    ]);

    $mobilityPage = $component->instance()->loadExerciseSelectorPrograms('calendar_program_selection', 'programs', 'Mobility');
    $fullPage = $component->instance()->loadExerciseSelectorPrograms('calendar_program_selection', 'programs');

    expect(collect($mobilityPage['records'] ?? [])->pluck('key')->all())->toContain($warmUpProgram->id)
        ->and(collect($fullPage['records'] ?? [])->pluck('key')->all())->toContain($warmUpProgram->id, $mainProgram->id, $coolDownProgram->id);
});

it('imports warm-up and cool-down source programs from the selector programs tab', function () {
    $group = UserGroup::create(['name' => 'Ski Team']);
    $program = ExerciseProgram::factory()->create([
        'name' => 'Mobility Lower Body',
        'type' => ExerciseProgramTypeEnum::WarmUp,
    ]);

    Livewire::test(CalendarAddProgramModal::class, [
        'groupId' => $group->id,
        'userId' => null,
    ])
        ->call('addCalendarProgramFromSelector', 'calendar_program_selection', 'programs', [
            'key' => (string) $program->id,
        ])
        ->assertSet('data.calendar_program_selection', [])
        ->assertDispatched('programs-changed');

    $trainingProgram = TrainingProgram::query()->sole();
    $clonedProgram = ExerciseProgram::findOrFail($trainingProgram->exercise_program_id);

    expect($trainingProgram->group_id)->toBe($group->id)
        ->and($clonedProgram->id)->not->toBe($program->id)
        ->and($clonedProgram->name)->toBe('Mobility Lower Body')
        ->and($clonedProgram->type)->toBe(ExerciseProgramTypeEnum::WarmUp)
        ->and($clonedProgram->parent_type)->toBe(TrainingProgram::class)
        ->and($clonedProgram->parent_id)->toBe($trainingProgram->id);
});

it('resets stale selector state every time the add program flow opens', function () {
    $group = UserGroup::create(['name' => 'Ski Team']);

    Livewire::test(CalendarAddProgramModal::class, [
        'groupId' => $group->id,
        'userId' => null,
    ])
        ->set('data.calendar_program_selection', [
            ['_key' => 'stale-a', 'id' => 21, 'sort' => 0, 'group' => null],
            ['_key' => 'stale-b', 'id' => 22, 'sort' => 1, 'group' => 'B'],
        ])
        ->set('relationshipSelectorSearch.calendar_program_selection', 'old query')
        ->set('relationshipSelectorTab.calendar_program_selection', 'selected')
        ->call('open')
        ->assertSet('data.calendar_program_selection', [])
        ->assertSet('relationshipSelectorSearch.calendar_program_selection', null)
        ->assertSet('relationshipSelectorTab.calendar_program_selection', null)
        ->assertDispatched('relationship-selector-open');
});

it('skips the hidden exercises preload when opening on the programs tab', function () {
    $group = UserGroup::create(['name' => 'Ski Team']);

    $component = Livewire::test(CalendarAddProgramModal::class, [
        'groupId' => $group->id,
        'userId' => null,
    ])
        ->set('data.calendar_program_selection', [
            ['_key' => 'stale-a', 'id' => 21, 'sort' => 0, 'group' => null],
        ])
        ->set('relationshipSelectorSearch.calendar_program_selection', 'old query')
        ->set('relationshipSelectorTab.calendar_program_selection', 'selected');

    $state = $component->instance()->relationshipSelectorClientInitialState('calendar_program_selection', 40);

    expect($state['initialListKey'])->toBe('programs')
        ->and($state['results'])->toBe([
            'records' => [],
            'nextOffset' => 0,
            'hasMore' => false,
        ])
        ->and($component->get('data.calendar_program_selection'))->toBe([])
        ->and($component->get('relationshipSelectorSearch.calendar_program_selection'))->toBeNull()
        ->and($component->get('relationshipSelectorTab.calendar_program_selection'))->toBeNull();
});

it('renders cached preview exercise badges for selector program rows', function () {
    $group = UserGroup::create(['name' => 'Ski Team']);
    $program = ExerciseProgram::factory()->create([
        'name' => 'Preview Builder',
        'type' => ExerciseProgramTypeEnum::Program,
    ]);

    foreach (range(1, 7) as $index) {
        ExerciseProgramExercise::create([
            'exercise_program_id' => $program->id,
            'exercise_id' => Exercise::factory()->create([
                'name' => 'Exercise '.$index,
            ])->id,
            'sort' => $index - 1,
            'type' => 'main',
        ]);
    }

    $page = Livewire::test(CalendarAddProgramModal::class, [
        'groupId' => $group->id,
        'userId' => null,
    ])->instance()->loadExerciseSelectorPrograms('calendar_program_selection', 'programs');

    $record = collect($page['records'] ?? [])->firstWhere('key', $program->id);
    $labels = collect(data_get($record, 'views.programs.columns.0.badges', []))
        ->pluck('label')
        ->all();

    expect($record)->not->toBeNull()
        ->and($labels)->toContain(
            'Exercise 1',
            'Exercise 2',
            'Exercise 3',
            'Exercise 4',
            'Exercise 5',
            'Exercise 6',
            '+1 more',
        )
        ->and(data_get($record, 'selector_program_exercise_count'))->toBe(7)
        ->and(data_get($record, 'selector_program_has_exercises'))->toBeTrue();
});

it('refreshes selector previews when an exercise name changes', function () {
    $group = UserGroup::create(['name' => 'Ski Team']);
    $program = ExerciseProgram::factory()->create([
        'name' => 'Rename Preview',
        'type' => ExerciseProgramTypeEnum::Program,
    ]);
    $exercise = Exercise::factory()->create(['name' => 'Old Name']);

    ExerciseProgramExercise::create([
        'exercise_program_id' => $program->id,
        'exercise_id' => $exercise->id,
        'sort' => 0,
        'type' => 'main',
    ]);

    $exercise->update(['name' => 'New Name']);

    $page = Livewire::test(CalendarAddProgramModal::class, [
        'groupId' => $group->id,
        'userId' => null,
    ])->instance()->loadExerciseSelectorPrograms('calendar_program_selection', 'programs');

    $record = collect($page['records'] ?? [])->firstWhere('key', $program->id);
    $labels = collect(data_get($record, 'views.programs.columns.0.badges', []))
        ->pluck('label')
        ->all();

    expect($program->fresh()->selector_preview_exercises)->toBe(['New Name'])
        ->and($labels)->toContain('New Name')
        ->and($labels)->not->toContain('Old Name');
});

it('creates a new calendar program from selected exercises and modal state', function () {
    $group = UserGroup::create(['name' => 'Ski Team']);
    $category = Tag::factory()->withScope('exercise_category')->create([
        'name' => 'Mobility',
        'sort_order' => 1,
    ]);
    $exerciseA = Exercise::factory()->create(['name' => 'Cat Cow']);
    $exerciseB = Exercise::factory()->create(['name' => 'Thoracic Rotation']);

    Livewire::test(CalendarAddProgramModal::class, [
        'groupId' => $group->id,
        'userId' => null,
    ])
        ->call('createCalendarProgramFromSelector', 'calendar_program_selection', [
            [
                '_key' => 'exercise-a',
                'id' => $exerciseA->id,
                'sort' => 1,
                'group' => 'B',
            ],
            [
                '_key' => 'exercise-b',
                'id' => $exerciseB->id,
                'sort' => 0,
                'group' => null,
            ],
        ], [
            'name' => 'Custom Mobility',
            'exercise_category_id' => (string) $category->id,
        ])
        ->assertDispatched('programs-changed');

    $trainingProgram = TrainingProgram::query()->sole();
    $createdProgram = ExerciseProgram::with('exercises')->findOrFail($trainingProgram->exercise_program_id);

    expect($createdProgram->name)->toBe('Custom Mobility')
        ->and($createdProgram->type)->toBe(ExerciseProgramTypeEnum::Program)
        ->and($createdProgram->exercise_category_id)->toBe($category->id)
        ->and($createdProgram->parent_type)->toBe(TrainingProgram::class)
        ->and($createdProgram->parent_id)->toBe($trainingProgram->id)
        ->and($createdProgram->exercises)->toHaveCount(2)
        ->and($createdProgram->exercises->pluck('id')->all())->toContain($exerciseA->id, $exerciseB->id);
});
