<?php

use Coda\Cms\Form\Field;
use Coda\Cms\Form\Fields\Number;
use Coda\Cms\Form\Fields\RadioSegmented;
use Coda\Cms\Form\Fields\Text;
use Coda\Cms\Form\Form;

it('includes conditional field when expression matches', function () {
    $form = Form::make()
        ->fieldset('General', [
            RadioSegmented::make('mode')
                ->options(['automatic' => 'Automatic', 'manual' => 'Manual'])
                ->live(),
            Number::make('oneRepMaxModifier')
                ->show('mode == "automatic"'),
        ]);

    $fieldsets = $form->resolveFieldsets(['mode' => 'automatic']);

    expect($fieldsets)->toHaveCount(1);
    expect($fieldsets[0]->fields)->toHaveCount(2);
    expect($fieldsets[0]->fields[1]->name)->toBe('oneRepMaxModifier');
});

it('excludes conditional field when expression does not match', function () {
    $form = Form::make()
        ->fieldset('General', [
            RadioSegmented::make('mode')
                ->options(['automatic' => 'Automatic', 'manual' => 'Manual'])
                ->live(),
            Number::make('oneRepMaxModifier')
                ->show('mode == "automatic"'),
        ]);

    $fieldsets = $form->resolveFieldsets(['mode' => 'manual']);

    expect($fieldsets)->toHaveCount(1);
    expect($fieldsets[0]->fields)->toHaveCount(1);
    expect($fieldsets[0]->fields[0]->name)->toBe('mode');
});

it('includes conditional fieldset when expression matches', function () {
    $form = Form::make()
        ->fieldset('General', [
            RadioSegmented::make('mode')
                ->options(['automatic' => 'Automatic', 'manual' => 'Manual'])
                ->live(),
        ])
        ->fieldset('Automatic Config', [
            Number::make('oneRepMaxModifier'),
        ], show: 'mode == "automatic"');

    $fieldsets = $form->resolveFieldsets(['mode' => 'automatic']);

    expect($fieldsets)->toHaveCount(2);
});

it('excludes conditional fieldset when expression does not match', function () {
    $form = Form::make()
        ->fieldset('General', [
            RadioSegmented::make('mode')
                ->options(['automatic' => 'Automatic', 'manual' => 'Manual'])
                ->live(),
        ])
        ->fieldset('Automatic Config', [
            Number::make('oneRepMaxModifier'),
        ], show: 'mode == "automatic"');

    $fieldsets = $form->resolveFieldsets(['mode' => 'manual']);

    expect($fieldsets)->toHaveCount(1);
    expect($fieldsets[0]->label)->toBe('General');
});

it('hidden fields do not produce validation rules', function () {
    $form = Form::make()
        ->fieldset('General', [
            RadioSegmented::make('mode')
                ->options(['automatic' => 'Automatic', 'manual' => 'Manual'])
                ->required()
                ->live(),
            Number::make('oneRepMaxModifier')
                ->required()
                ->show('mode == "automatic"'),
            Number::make('startingWeight')
                ->required()
                ->show('mode == "manual"'),
        ]);

    $fieldsets = $form->resolveFieldsets(['mode' => 'automatic']);
    $rules = Field::buildValidationRules($fieldsets[0]->fields, 'data.');

    expect($rules)->toHaveKey('data.mode');
    expect($rules)->toHaveKey('data.oneRepMaxModifier');
    expect($rules)->not->toHaveKey('data.startingWeight');
});

it('hidden fields do not produce defaults', function () {
    $form = Form::make()
        ->fieldset('General', [
            RadioSegmented::make('mode')
                ->default('automatic'),
            Number::make('oneRepMaxModifier')
                ->default(100)
                ->show('mode == "automatic"'),
            Number::make('startingWeight')
                ->default(60)
                ->show('mode == "manual"'),
        ]);

    $fieldsets = $form->resolveFieldsets(['mode' => 'automatic']);
    $defaults = Field::buildDefaults($fieldsets[0]->fields);

    expect($defaults)->toHaveKey('mode');
    expect($defaults)->toHaveKey('oneRepMaxModifier');
    expect($defaults)->not->toHaveKey('startingWeight');
});

it('fields without show expressions are always included', function () {
    $form = Form::make()
        ->fieldset('General', [
            Text::make('name'),
            Number::make('reps'),
        ]);

    $fieldsets = $form->resolveFieldsets([]);

    expect($fieldsets)->toHaveCount(1);
    expect($fieldsets[0]->fields)->toHaveCount(2);
});

it('uses field defaults to evaluate expressions when data is missing', function () {
    $form = Form::make()
        ->fieldset('Weight', [
            RadioSegmented::make('mode')
                ->options(['automatic' => 'Automatic', 'manual' => 'Manual'])
                ->default('automatic')
                ->live(),
            Number::make('oneRepMaxModifier')
                ->default(100)
                ->show('mode == "automatic"'),
        ], prefix: 'data.weight');

    $fieldsets = $form->resolveFieldsets([]);

    expect($fieldsets)->toHaveCount(1);
    expect($fieldsets[0]->fields)->toHaveCount(2);
    expect($fieldsets[0]->fields[1]->name)->toBe('oneRepMaxModifier');

    $defaults = Field::buildDefaults($fieldsets[0]->fields);
    expect($defaults)->toBe(['mode' => 'automatic', 'oneRepMaxModifier' => 100]);
});

it('tracks hidden field names on resolved fieldsets', function () {
    $form = Form::make()
        ->fieldset('General', [
            RadioSegmented::make('mode')
                ->options(['automatic' => 'Automatic', 'manual' => 'Manual'])
                ->default('automatic')
                ->live(),
            Number::make('oneRepMaxModifier')
                ->default(100)
                ->show('mode == "automatic"'),
            Number::make('startingWeight')
                ->default(60)
                ->show('mode == "manual"'),
        ]);

    $fieldsets = $form->resolveFieldsets(['mode' => 'automatic']);

    expect($fieldsets[0]->hiddenFieldNames)->toBe(['startingWeight']);

    $fieldsets = $form->resolveFieldsets(['mode' => 'manual']);

    expect($fieldsets[0]->hiddenFieldNames)->toBe(['oneRepMaxModifier']);
});

it('scopes field expressions to fieldset prefix data', function () {
    $form = Form::make()
        ->fieldset('Weight', [
            RadioSegmented::make('mode')
                ->options(['automatic' => 'Automatic', 'manual' => 'Manual'])
                ->live(),
            Number::make('oneRepMaxModifier')
                ->show('mode == "automatic"'),
        ], prefix: 'data.weight');

    $fieldsets = $form->resolveFieldsets(['weight' => ['mode' => 'automatic', 'oneRepMaxModifier' => 100]]);

    expect($fieldsets)->toHaveCount(1);
    expect($fieldsets[0]->fields)->toHaveCount(2);

    $fieldsets = $form->resolveFieldsets(['weight' => ['mode' => 'manual', 'oneRepMaxModifier' => 100]]);

    expect($fieldsets)->toHaveCount(1);
    expect($fieldsets[0]->fields)->toHaveCount(1);
    expect($fieldsets[0]->fields[0]->name)->toBe('mode');
});
