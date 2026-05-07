<?php

use App\Form\Fields\Exercise\Exercises;
use App\Models\Exercise\Exercise;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('returns only the current selection when the exercise search query is empty', function () {
    $selected = Exercise::factory()->create(['name' => 'Selected Exercise']);
    Exercise::factory()->count(3)->create();

    $field = (new Exercises('exercises'))->withSearch();

    $results = collect($field->getSearchResults('', $selected->id, []));

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($selected->id);
});

it('returns no exercise options for an empty query without a current selection', function () {
    Exercise::factory()->count(3)->create();

    $field = (new Exercises('exercises'))->withSearch();

    $results = collect($field->getSearchResults('', null, []));

    expect($results)->toBeEmpty();
});

it('returns matching exercise results when a query is provided', function () {
    $matching = Exercise::factory()->create(['name' => 'Jogging Tempo']);
    Exercise::factory()->create(['name' => 'Bench Press']);

    $field = (new Exercises('exercises'))->withSearch();

    $results = collect($field->getSearchResults('jogg', null, []));

    expect($results->pluck('id')->all())->toContain($matching->id);
});
