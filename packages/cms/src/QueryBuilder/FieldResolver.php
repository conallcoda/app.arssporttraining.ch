<?php

namespace Coda\Cms\QueryBuilder;

use Symfony\Component\ExpressionLanguage\Node\Node;

interface FieldResolver
{
    public function names(): array;

    /** @return array{type: string, ...} */
    public function resolve(Node $node): array;
}
