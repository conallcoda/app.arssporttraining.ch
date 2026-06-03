<?php

use Coda\FormKit\Field;
use Coda\FormKit\Fields\RelationshipSelector;
use Coda\FormKit\Fields\Select;
use Coda\FormKit\Fields\Text;

it('falls back to option lists for search and selected records', function () {
    $field = RelationshipSelector::make('members')
        ->options([
            10 => 'Alice Smith',
            11 => 'Bob Jones',
            12 => 'Charlie Stone',
        ]);

    $searchResults = collect($field->getSearchResults('bob'));
    $selectedRecords = collect($field->getSelectedRecords([12, 10]));

    expect($searchResults)->toHaveCount(1)
        ->and($field->resolveRecordKey($searchResults->first()))->toBe(11)
        ->and($field->resolveRecordLabel($searchResults->first()))->toBe('Bob Jones')
        ->and($selectedRecords->map(fn ($record) => $field->resolveRecordKey($record))->all())->toBe([10, 12]);
});

it('passes supported callback arguments to relationship selector search callbacks', function () {
    $field = RelationshipSelector::make('exercises')
        ->searchable(function (string $query, array $selectedIds, array $excludedIds, array $filters) {
            return [[
                'id' => 5,
                'name' => implode('|', [$query, implode(',', $selectedIds), implode(',', $excludedIds), (string) ($filters['category'] ?? '')]),
            ]];
        });

    $results = collect($field->getSearchResults('split squat', ['9', '12'], ['3'], ['category' => 'legs']));

    expect($results)->toHaveCount(1)
        ->and($field->resolveRecordLabel($results->first()))->toBe('split squat|9,12|3|legs');
});

it('builds validation rules for inline relationship selector schema fields', function () {
    $field = RelationshipSelector::make('exercises')
        ->schema([
            Select::make('group')->required(),
            Text::make('note')->rules('nullable|string|max:120'),
        ]);

    $rules = Field::buildValidationRules([$field], 'data.');
    $attributes = Field::buildValidationAttributes([$field], 'data.');

    expect($rules)->toHaveKey('data.exercises.*.group')
        ->and($rules)->toHaveKey('data.exercises.*.note')
        ->and($attributes)->toHaveKey('data.exercises.*.group')
        ->and($attributes)->toHaveKey('data.exercises.*.note');
});
