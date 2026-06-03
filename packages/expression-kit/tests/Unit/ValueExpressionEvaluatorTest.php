<?php

use Coda\ExpressionKit\ValueExpressionEvaluator;

it('evaluates scalar expressions', function () {
    $evaluator = new ValueExpressionEvaluator;

    expect($evaluator->evaluate('reps > 5 ? "high" : "low"', ['reps' => 8]))
        ->toBe('high');
});

it('returns the fallback when value expressions fail', function () {
    $evaluator = new ValueExpressionEvaluator;

    expect($evaluator->evaluate('unknown +++ broken', ['reps' => 8], 'fallback'))
        ->toBe('fallback');
});
