<?php

namespace Coda\ExpressionKit;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\ParsedExpression;
use Symfony\Component\ExpressionLanguage\SyntaxError;

class ExpressionParser
{
    private ExpressionLanguage $language;

    public function __construct()
    {
        $this->language = new ExpressionLanguage;
    }

    /**
     * @param  string[]  $names
     */
    public function parse(string $expression, array $names = []): ParsedExpression
    {
        try {
            return $this->language->parse($expression, $names);
        } catch (SyntaxError $e) {
            throw new \InvalidArgumentException($e->getMessage(), 0, $e);
        }
    }

    /**
     * @param  string[]  $reserved
     * @return string[]
     */
    public function extractIdentifiers(string $expression, array $reserved = []): array
    {
        preg_match_all('/\b([a-zA-Z_][a-zA-Z0-9_]*)\b/', $expression, $matches);

        return array_values(array_unique(array_filter(
            $matches[1],
            static fn (string $name): bool => ! in_array($name, $reserved, true)
        )));
    }
}
