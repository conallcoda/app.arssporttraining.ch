<?php

use App\Form\Fields\AthleteGroup\Members;
use App\Form\Fields\Exercise\Exercises;
use App\Models\Exercise\Exercise;
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

it('defaults member selectors to the client modal path', function () {
    $field = Members::make('members')->withOptions();
    $lists = $field->getClientModalListPayload();

    expect($field->clientModal)->toBeTrue()
        ->and($field->deferModalApply)->toBeTrue()
        ->and($lists[0]['rowAction'])->toBe([])
        ->and($lists[1]['rowAction'])->toBe([]);
});
