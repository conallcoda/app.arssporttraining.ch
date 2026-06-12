<?php

namespace Coda\ExpressionKit;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class ValueExpressionEvaluator
{
    private ExpressionLanguage $language;

    public function __construct()
    {
        $this->language = new ExpressionLanguage;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function evaluate(string $expression, array $context = [], mixed $fallback = null): mixed
    {
        try {
            return $this->language->evaluate($expression, $context);
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
