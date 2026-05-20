<?php

use Coda\FormKit\Fields\Select;

it('loads tree select options using context', function () {
    $field = Select::make('taxonomy')
        ->tree()
        ->treeOptionsUsing(fn (array $context) => [
            [
                'value' => 1,
                'name' => 'Edition '.$context['edition_id'],
                'children' => [],
            ],
        ]);

    expect($field->getTreeOptions(['edition_id' => 2026]))->toBe([
        [
            'value' => 1,
            'name' => 'Edition 2026',
            'children' => [],
        ],
    ]);
});

it('can exclude a single artificial root from select tree options', function () {
    $field = Select::make('taxonomy')
        ->tree()
        ->treeExcludeRoot()
        ->treeOptions([
            [
                'value' => 1,
                'name' => 'Interests',
                'children' => [
                    ['value' => 2, 'name' => 'Capital Allocator', 'children' => []],
                    ['value' => 3, 'name' => 'Custody', 'children' => []],
                ],
            ],
        ]);

    expect($field->getRenderableTreeOptions())->toBe([
        ['value' => 2, 'name' => 'Capital Allocator', 'children' => []],
        ['value' => 3, 'name' => 'Custody', 'children' => []],
    ]);
});

it('can mark a select tree as leaf only', function () {
    $field = Select::make('taxonomy')
        ->tree()
        ->treeLeafOnly();

    expect($field->treeLeafOnly)->toBeTrue();
});
