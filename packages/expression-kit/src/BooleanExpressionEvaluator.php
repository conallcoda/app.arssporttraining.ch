<?php

namespace Coda\ExpressionKit;

class BooleanExpressionEvaluator
{
    private ValueExpressionEvaluator $evaluator;

    public function __construct()
    {
        $this->evaluator = new ValueExpressionEvaluator;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function evaluate(string $expression, array $context = []): bool
    {
        return (bool) $this->evaluator->evaluate($expression, $context, false);
    }
}
