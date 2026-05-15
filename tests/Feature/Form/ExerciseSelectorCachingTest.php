<?php

use App\Form\Fields\Exercise\Exercises;
use App\Models\Exercise\Exercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('caches exercise selector options across field instances in one request', function () {
    Exercise::factory()->count(3)->create();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $first = Exercises::make('exercises')->withOptions();
    $second = Exercises::make('exercises')->withOptions();

    $first->getOptions();
    $second->getOptions();

    $queries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $query) => str_contains($query, 'from "exercises"') || str_contains($query, 'from `exercises`'))
        ->values();

    DB::disableQueryLog();

    expect($queries->count())->toBeLessThanOrEqual(1);
});

it('caches selected exercise records across repeated selector lookups in one request', function () {
    $exercises = Exercise::factory()->count(3)->create();
    $field = Exercises::make('exercises')->withSearch();
    $selectedIds = $exercises->pluck('id')->all();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $field->getSelectedRecords($selectedIds);
    $field->getSelectedRecords($selectedIds);

    $queries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $query) => str_contains($query, 'from "exercises"') || str_contains($query, 'from `exercises`'))
        ->values();

    DB::disableQueryLog();

    expect($queries->count())->toBeLessThanOrEqual(1);
});
