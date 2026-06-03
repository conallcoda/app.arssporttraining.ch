<?php

namespace Coda\SchemaKit;

use Closure;

final class WeightedCategoryTreeInput extends InputDefinition
{
    private array|Closure|null $options = null;

    private ?string $topicType = null;

    private ?array $range = null;

    private ?array $ticks = null;

    private ?string $emptyText = null;

    private ?string $factory = null;

    public static function make(): static
    {
        return new static;
    }

    public function options(array|Closure|null $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function topicType(?string $topicType): static
    {
        $this->topicType = $topicType;

        return $this;
    }

    public function range(int|float $min, int|float $max, int|float $step, int|float|null $initial = null): static
    {
        $this->range = [$min, $max, $step, $initial];

        return $this;
    }

    public function ticks(?array $ticks): static
    {
        $this->ticks = $ticks;

        return $this;
    }

    public function emptyText(?string $emptyText): static
    {
        $this->emptyText = $emptyText;

        return $this;
    }

    public function factory(?string $factory): static
    {
        $this->factory = $factory;

        return $this;
    }

    public function getOptions(): array|Closure|null
    {
        return $this->options;
    }

    public function getTopicType(): ?string
    {
        return $this->topicType;
    }

    public function getRange(): ?array
    {
        return $this->range;
    }

    public function getTicks(): ?array
    {
        return $this->ticks;
    }

    public function getEmptyText(): ?string
    {
        return $this->emptyText;
    }

    public function getFactory(): ?string
    {
        return $this->factory;
    }

    public function kind(): string
    {
        return 'weighted_category_tree';
    }
}
