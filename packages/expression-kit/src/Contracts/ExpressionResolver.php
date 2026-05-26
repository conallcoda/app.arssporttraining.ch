<?php

namespace Coda\ExpressionKit\Contracts;

use Coda\ExpressionKit\ResolvedExpressionReference;
use Symfony\Component\ExpressionLanguage\Node\Node;

interface ExpressionResolver
{
    /**
     * @return string[]
     */
    public function names(): array;

    public function resolve(Node $node): ResolvedExpressionReference;
}
