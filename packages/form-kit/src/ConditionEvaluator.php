<?php

namespace Coda\FormKit;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class ConditionEvaluator
{
    private ExpressionLanguage $language;

    public function __construct()
    {
        $this->language = new ExpressionLanguage;
    }

    public function evaluate(string $expression, array $data): bool
    {
        try {
            return (bool) $this->language->evaluate($expression, $data);
        } catch (\Throwable) {
            return false;
        }
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
