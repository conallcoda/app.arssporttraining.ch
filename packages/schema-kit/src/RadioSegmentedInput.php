<?php

namespace Coda\SchemaKit;

use Closure;

final class RadioSegmentedInput extends InputDefinition
{
    private array|Closure|null $options = null;

    private bool $live = false;

    public static function make(): static
    {
        return new static;
    }

    public function options(array|Closure|null $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function live(bool $live = true): static
    {
        $this->live = $live;

        return $this;
    }

    public function getOptions(): array|Closure|null
    {
        return $this->options;
    }

    public function isLive(): bool
    {
        return $this->live;
    }

    public function kind(): string
    {
        return 'radio_segmented';
    }
}
