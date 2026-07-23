<?php

namespace Coda\Cms\Display;

class CardField
{
    public ?string $label = null;

    public ?string $view = null;

    public ?\Closure $valueResolver = null;

    public bool $showLabel = true;

    public string $variant = 'text';

    public string $aspect = 'square';

    public string $fit = 'cover';

    public bool $insetImage = false;

    public string $imageInsetClass = 'p-4';

    public ?string $imageContainerClass = null;

    public function __construct(
        public string $field,
    ) {}

    public static function make(string $field): static
    {
        return new static($field);
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function view(string $view): static
    {
        $this->view = $view;

        return $this;
    }

    public function resolveUsing(callable $resolver): static
    {
        $this->valueResolver = $resolver(...);

        return $this;
    }

    public function hideLabel(): static
    {
        $this->showLabel = false;

        return $this;
    }

    public function variant(string $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    public function badge(): static
    {
        return $this->variant('badge');
    }

    public function aspect(string $aspect): static
    {
        $this->aspect = $aspect;

        return $this;
    }

    public function fit(string $fit): static
    {
        $this->fit = $fit;

        return $this;
    }

    public function cover(): static
    {
        return $this->fit('cover');
    }

    public function contain(): static
    {
        return $this->fit('contain');
    }

    public function inset(string $paddingClass = 'p-4'): static
    {
        $this->insetImage = true;
        $this->imageInsetClass = $paddingClass;

        return $this;
    }

    public function imageContainerClass(string $class): static
    {
        $this->imageContainerClass = $class;

        return $this;
    }

    public function meta(): static
    {
        return $this->variant('meta');
    }

    public function resolvedLabel(): string
    {
        return $this->label ?? str($this->field)->headline()->toString();
    }

    public function resolveValue(mixed $record): mixed
    {
        if ($this->valueResolver !== null) {
            return ($this->valueResolver)($record);
        }

        return data_get($record, $this->field);
    }
}
