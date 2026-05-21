<?php

namespace Coda\FormKit\Concerns;

trait HasCondition
{
    public ?string $showExpression = null;

    public function show(string $expression): static
    {
        $this->showExpression = $expression;

        return $this;
    }

    public function hasShowExpression(): bool
    {
        return $this->showExpression !== null;
    }
}
