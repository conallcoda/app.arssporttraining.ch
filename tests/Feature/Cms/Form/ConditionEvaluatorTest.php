<?php

use App\Cms\Form\ConditionEvaluator;
use App\Cms\Form\Fields\Number;
use App\Cms\Form\Fields\Text;

it('evaluates equality expression as true', function () {
    $evaluator = new ConditionEvaluator;

    expect($evaluator->evaluate('mode == "automatic"', ['mode' => 'automatic']))->toBeTrue();
});

it('evaluates equality expression as false', function () {
    $evaluator = new ConditionEvaluator;

    expect($evaluator->evaluate('mode == "automatic"', ['mode' => 'manual']))->toBeFalse();
});

it('evaluates not-equal expression', function () {
    $evaluator = new ConditionEvaluator;

    expect($evaluator->evaluate('mode != "manual"', ['mode' => 'automatic']))->toBeTrue();
    expect($evaluator->evaluate('mode != "manual"', ['mode' => 'manual']))->toBeFalse();
});

it('evaluates numeric comparison', function () {
    $evaluator = new ConditionEvaluator;

    expect($evaluator->evaluate('weight > 80', ['weight' => 100]))->toBeTrue();
    expect($evaluator->evaluate('weight > 80', ['weight' => 50]))->toBeFalse();
});

it('evaluates compound expressions with and', function () {
    $evaluator = new ConditionEvaluator;

    expect($evaluator->evaluate('mode == "automatic" and reps > 5', ['mode' => 'automatic', 'reps' => 8]))->toBeTrue();
    expect($evaluator->evaluate('mode == "automatic" and reps > 5', ['mode' => 'automatic', 'reps' => 3]))->toBeFalse();
});

it('evaluates or expressions', function () {
    $evaluator = new ConditionEvaluator;

    expect($evaluator->evaluate('mode == "automatic" or mode == "hybrid"', ['mode' => 'hybrid']))->toBeTrue();
    expect($evaluator->evaluate('mode == "automatic" or mode == "hybrid"', ['mode' => 'manual']))->toBeFalse();
});

it('returns false for invalid expressions', function () {
    $evaluator = new ConditionEvaluator;

    expect($evaluator->evaluate('totally %%% invalid', ['mode' => 'automatic']))->toBeFalse();
});

it('returns false when referenced variable is missing', function () {
    $evaluator = new ConditionEvaluator;

    expect($evaluator->evaluate('mode == "automatic"', []))->toBeFalse();
});

it('filters fields removing hidden ones', function () {
    $evaluator = new ConditionEvaluator;

    $visible = Text::make('name');
    $shown = Number::make('reps')->show('mode == "automatic"');
    $hidden = Number::make('weight')->show('mode == "manual"');

    $result = $evaluator->filterFields([$visible, $shown, $hidden], ['mode' => 'automatic']);

    expect($result)->toHaveCount(2);
    expect($result[0]->name)->toBe('name');
    expect($result[1]->name)->toBe('reps');
});

it('keeps all fields when none have show expressions', function () {
    $evaluator = new ConditionEvaluator;

    $fields = [
        Text::make('name'),
        Number::make('reps'),
    ];

    $result = $evaluator->filterFields($fields, []);

    expect($result)->toHaveCount(2);
});
