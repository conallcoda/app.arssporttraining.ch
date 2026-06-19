<?php

namespace Coda\FormKit;

use Coda\ExpressionKit\BooleanExpressionEvaluator;

class ConditionEvaluator
{
    private BooleanExpressionEvaluator $evaluator;

    public function __construct()
    {
        $this->evaluator = new BooleanExpressionEvaluator;
    }

    public function evaluate(string $expression, array $data): bool
    {
        return $this->evaluator->evaluate($expression, $data);
    }

    public function filterFields(array $fields, array $data): array
    {
        return array_values(array_filter($fields, function (Field $field) use ($data) {
            if (! $field->hasShowExpression()) {
                return true;
            }

            return $this->evaluate($field->showExpression, $data);
        }));
    }
}
