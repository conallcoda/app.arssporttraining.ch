<?php

namespace App\Training\Derivation;

class AutomaticStrategyResolution
{
    /** @param  array<string, ResolvedGridField>  $fields */
    public function __construct(
        public array $fields,
    ) {}

    public function field(string $name): ?ResolvedGridField
    {
        return $this->fields[$name] ?? null;
    }
}
