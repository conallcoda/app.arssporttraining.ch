<?php

use Coda\FormKit\Fields\RadioSegmented;
use Coda\FormKit\Fields\Search;
use Coda\FormKit\Fields\Text;

it('resolves render views from kebab-cased field types by default', function () {
    $field = RadioSegmented::make('mode');

    expect($field->getRenderViewKey())->toBe('radio-segmented')
        ->and($field->getRenderView())->toBe('form-kit::components.form.fields.radio-segmented');
});

it('returns null for placeholders that were not explicitly set', function () {
    $field = Text::make('name')->label('Name');

    expect($field->getPlaceholder())->toBe('Name')
        ->and($field->getExplicitPlaceholder())->toBeNull();
});

it('allows fields to override their renderer view explicitly', function () {
    $field = Text::make('name')->view('custom.fields.name');

    expect($field->getRenderView())->toBe('custom.fields.name');
});

it('uses semantic update policies to build wire:model directives', function () {
    $field = Text::make('name');

    expect($field->wireModelDirective())->toBe('wire:model.blur.live');

    $field->updateOn('live', 250);
    expect($field->wireModelDirective())->toBe('wire:model.live.debounce.250ms');

    $field->change();
    expect($field->wireModelDirective())->toBe('wire:model.change.live');

    $field->live(false);
    expect($field->wireModelDirective())->toBe('wire:model');
});

it('configures search fields for live debounced updates by default', function () {
    $field = Search::make('query');

    expect($field->wireModelDirective())->toBe('wire:model.live.debounce.300ms')
        ->and($field->updateOn)->toBe('live')
        ->and($field->debounceMs)->toBe(300);
});
