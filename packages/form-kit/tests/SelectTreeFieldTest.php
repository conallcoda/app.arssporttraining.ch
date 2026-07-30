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

it('resolves uncached tree select options for the current context', function () {
    $field = Select::make('taxonomy')
        ->tree()
        ->treeOptionsUsing(fn (array $context) => [
            [
                'value' => $context['edition_id'],
                'name' => 'Edition '.$context['edition_id'],
                'children' => [],
            ],
        ]);

    expect($field->getTreeOptions(['edition_id' => 2026]))->toBe([
        [
            'value' => 2026,
            'name' => 'Edition 2026',
            'children' => [],
        ],
    ])->and($field->getTreeOptions(['edition_id' => 2027]))->toBe([
        [
            'value' => 2027,
            'name' => 'Edition 2027',
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

it('can flatten tree select options using context', function () {
    $field = Select::make('taxonomy')
        ->tree()
        ->treeOptionsUsing(fn (array $context) => [
            [
                'value' => 1,
                'name' => 'Edition '.$context['edition_id'],
                'children' => [
                    [
                        'value' => 2,
                        'name' => 'Speaker',
                        'children' => [],
                    ],
                ],
            ],
        ]);

    expect($field->flatTreeOptions(['edition_id' => 2026]))->toBe([
        1 => 'Edition 2026',
        2 => 'Edition 2026 / Speaker',
    ]);
});
