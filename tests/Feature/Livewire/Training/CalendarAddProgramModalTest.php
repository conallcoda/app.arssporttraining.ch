<?php

use App\Livewire\Training\CalendarAddProgramModal;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
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
