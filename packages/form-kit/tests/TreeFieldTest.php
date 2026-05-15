<?php

use Coda\FormKit\Fields\Tree;

it('loads tree options using context', function () {
    $field = Tree::make('taxonomy')->optionsUsing(fn (array $context) => [
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

it('keeps the root visible by default', function () {
    $field = Tree::make('taxonomy')->options([
        [
            'value' => 1,
            'name' => 'Attendee',
            'children' => [
                ['value' => 2, 'name' => 'Participant', 'children' => []],
            ],
        ],
    ]);

    expect($field->getRenderableTreeOptions())->toBe([
        [
            'value' => 1,
            'name' => 'Attendee',
            'children' => [
                ['value' => 2, 'name' => 'Participant', 'children' => []],
            ],
        ],
    ]);
});

it('can exclude a single artificial root from rendered tree options', function () {
    $field = Tree::make('taxonomy')
        ->excludeRoot()
        ->options([
            [
                'value' => 1,
                'name' => 'Attendee',
                'children' => [
                    ['value' => 2, 'name' => 'Participant', 'children' => []],
                    ['value' => 3, 'name' => 'Speaker', 'children' => []],
                ],
            ],
        ]);

    expect($field->getRenderableTreeOptions())->toBe([
        ['value' => 2, 'name' => 'Participant', 'children' => []],
        ['value' => 3, 'name' => 'Speaker', 'children' => []],
    ]);
});
