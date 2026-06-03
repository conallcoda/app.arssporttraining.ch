<?php

namespace Coda\ExpressionKit;

final class ExpressionValidationResult
{
    /**
     * @param  string[]  $unknownPaths
     */
    public function __construct(
        public readonly ?string $syntaxError = null,
        public readonly array $unknownPaths = [],
    ) {}

    public function isValid(): bool
    {
        return $this->syntaxError === null && $this->unknownPaths === [];
    }
}
