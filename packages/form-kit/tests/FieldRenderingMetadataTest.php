<?php

use Coda\FormKit\Fields\DocumentWriter;
use Coda\FormKit\Fields\RadioSegmented;
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

it('initializes document writer with a shared renderer and array validation', function () {
    $field = DocumentWriter::make('document')->minHeight(320)->chartTypes(['bar', 'pie']);

    expect($field->getRenderView())->toBe('form-kit::components.form.fields.document-writer')
        ->and($field->validationRules)->toBe(['array'])
        ->and($field->minHeight)->toBe(320)
        ->and($field->chartTypes)->toBe(['bar', 'pie']);
});
