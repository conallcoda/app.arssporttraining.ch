<?php

namespace Coda\Cms\Data;

use Closure;

class TreeNodeTypeData extends AbstractData
{
    public ?Closure $prepareData = null;

    public ?Closure $visibleWhen = null;

    public function __construct(
        public string $key,
        public string $label,
        public string $formDataClass,
        public ?string $handler = null,
        public ?string $icon = 'plus',
        public ?string $modalTitle = null,
        public ?string $submitLabel = 'Save',
    ) {}

    public static function make(string $key, string $label, string $formDataClass): static
    {
        return new static($key, $label, $formDataClass);
    }

    public function handler(string $handler): static
    {
        $this->handler = $handler;

        return $this;
    }

    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function modalTitle(string $modalTitle): static
    {
        $this->modalTitle = $modalTitle;

        return $this;
    }

    public function submitLabel(string $submitLabel): static
    {
        $this->submitLabel = $submitLabel;

        return $this;
    }

    public function prepareData(Closure $callback): static
    {
        $this->prepareData = $callback;

        return $this;
    }

    public function visibleWhen(Closure $callback): static
    {
        $this->visibleWhen = $callback;

        return $this;
    }

    public function isVisible(?TreeNodeData $parent = null): bool
    {
        if ($this->visibleWhen === null) {
            return true;
        }

        return (bool) ($this->visibleWhen)($parent);
    }

    public function resolvedHandler(): string
    {
        return $this->handler ?? 'handleFormSubmitted';
    }
}
