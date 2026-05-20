<?php

use App\Form\Fields\AthleteGroup\Members;
use App\Form\Fields\Exercise\Exercises;
use App\Form\Fields\Training\Program\ExerciseCategory;
use App\Form\Fields\Training\Program\ProgramName;
use App\Models\Exercise\Exercise;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('uses client modal list defaults for exercise selectors', function () {
    $field = Exercises::make('exercises')->withSearch();
    $lists = $field->getClientModalListPayload();

    expect($field->clientModal)->toBeTrue()
        ->and($field->deferModalApply)->toBeTrue()
        ->and($field->triggerButtonLabel)->toBe('Edit')
        ->and($field->triggerButtonIcon)->toBe('pencil')
        ->and($lists)->toHaveCount(3)
        ->and($lists[0]['key'])->toBe('exercises')
        ->and($lists[0]['rowAction'])->toBe([])
        ->and($lists[0]['loader']['type'])->toBe('default')
        ->and($lists[0]['button']['defaultLabel'])->toBe('Select')
        ->and($lists[1]['key'])->toBe('programs')
        ->and($lists[1]['rowAction'])->toBe([])
        ->and($lists[1]['loader']['method'])->toBe('loadExerciseSelectorPrograms')
        ->and($lists[1]['button']['defaultLabel'])->toBe('Import')
        ->and($lists[2]['key'])->toBe('selected')
        ->and($lists[2]['rowAction'])->toBe([])
        ->and($lists[2]['sortable'])->toBeTrue()
        ->and($lists[2]['badge']['mode'])->toBe('selected-count')
        ->and($lists[2]['itemFields'][0]['key'])->toBe('group');
});

it('serializes per-list client modal views for selector rows', function () {
    $field = Exercises::make('exercises')->withSearch();
    $exercise = Exercise::factory()->create();

    $serialized = $field->serializeRecordForClientModal($exercise);

    expect($serialized['views'])->toHaveKeys(['exercises', 'programs', 'selected'])
        ->and($serialized['views']['exercises']['columns'])->not->toBeEmpty()
        ->and($serialized['views']['programs']['columns'])->not->toBeEmpty()
        ->and($serialized['views']['selected']['columns'])->not->toBeEmpty();
});

it('serializes exercise selector category and modifiers as badges', function () {
    $field = Exercises::make('exercises')->withSearch();

    $category = Tag::factory()->create([
        'scope' => 'exercise_category',
        'name' => 'Ski',
        'short_name' => 'SKI',
        'color' => 'blue',
    ]);

    $modifier = Tag::factory()->create([
        'scope' => 'exercise_modifiers',
        'name' => 'Hand on hip',
        'short_name' => 'HOH',
    ]);

    $exercise = Exercise::factory()->create([
        'category_id' => $category->id,
    ]);

    $exercise->modifiers()->sync([$modifier->id]);
    $exercise->load(['category', 'modifiers']);

    $serialized = $field->serializeRecordForClientModal($exercise);
    $exerciseBadges = $serialized['views']['exercises']['columns'][0]['badges'] ?? [];
    $selectedBadges = $serialized['views']['selected']['columns'][0]['badges'] ?? [];

    expect($exerciseBadges)->toHaveCount(2)
        ->and(collect($exerciseBadges)->pluck('label')->all())->toBe(['SKI', 'Hand on hip'])
        ->and($selectedBadges)->toHaveCount(2)
        ->and(collect($selectedBadges)->pluck('label')->all())->toBe(['SKI', 'Hand on hip']);
});

it('searches exercises by name, category, modifiers, and internal tags', function () {
    Exercises::flushRequestCaches();

    $field = Exercises::make('exercises')->withSearch();

    $category = Tag::factory()->create([
        'scope' => 'exercise_category',
        'name' => 'Plyometric',
    ]);

    $modifier = Tag::factory()->create([
        'scope' => 'exercise_modifiers',
        'name' => 'Hand on hip',
    ]);

    $internalTag = Tag::factory()->create([
        'scope' => 'exercise_internal',
        'name' => 'Travel Block',
    ]);

    $exercise = Exercise::factory()->create([
        'name' => 'Skischuh Squatjump',
        'category_id' => $category->id,
    ]);

    $exercise->modifiers()->syncWithoutDetaching([$modifier->id]);
    $exercise->internalTags()->syncWithoutDetaching([$internalTag->id]);

    $nameResults = collect($field->getSearchResults('Squatjump'))->pluck('name')->all();
    $categoryResults = collect($field->getSearchResults('Plyometric'))->pluck('name')->all();
    $modifierResults = collect($field->getSearchResults('Hand on hip'))->pluck('name')->all();
    $tagResults = collect($field->getSearchResults('Travel Block'))->pluck('name')->all();

    expect($nameResults)->toContain('Skischuh Squatjump')
        ->and($categoryResults)->toContain('Skischuh Squatjump')
        ->and($modifierResults)->toContain('Skischuh Squatjump')
        ->and($tagResults)->toContain('Skischuh Squatjump');
});

it('defaults member selectors to the client modal path', function () {
    $field = Members::make('members')->withOptions();
    $lists = $field->getClientModalListPayload();

    expect($field->clientModal)->toBeTrue()
        ->and($field->deferModalApply)->toBeTrue()
        ->and($lists[0]['rowAction'])->toBe([])
        ->and($lists[1]['rowAction'])->toBe([]);
});

it('serializes real field definitions for client modal panel fields', function () {
    $category = Tag::factory()->create([
        'scope' => 'exercise_category',
        'name' => 'Ski',
        'color' => 'blue',
        'sort_order' => 1,
    ]);

    $field = Exercises::make('calendar_program_selection')
        ->clientModalStateFields([
            [
                'field' => ProgramName::make('name')->label('Program Name'),
                'listKey' => 'selected',
            ],
            [
                'field' => ExerciseCategory::make('exercise_category_id')->withOptions(),
                'listKey' => 'selected',
            ],
        ]);

    $selectedList = collect($field->getClientModalListPayload())->firstWhere('key', 'selected');
    $panelFields = $selectedList['panelFields'] ?? [];

    expect($panelFields)->toHaveCount(2)
        ->and($panelFields[0]['key'])->toBe('name')
        ->and($panelFields[0]['label'])->toBe('Program Name')
        ->and($panelFields[0]['required'])->toBeTrue()
        ->and($panelFields[0]['type'])->toBe('text')
        ->and($panelFields[1]['key'])->toBe('exercise_category_id')
        ->and($panelFields[1]['label'])->toBe('Category')
        ->and($panelFields[1]['required'])->toBeTrue()
        ->and($panelFields[1]['type'])->toBe('select')
        ->and($panelFields[1]['optionView'])->toBe('form.options.program-category')
        ->and($panelFields[1]['fieldMeta']['colorMap'][$category->id] ?? null)->toBe('blue')
        ->and(collect($panelFields[1]['options'])->pluck('label', 'value')->all()[(string) $category->id] ?? null)->toBe('Ski');
});
