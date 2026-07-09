<?php

namespace Coda\Cms\Data;

use Closure;

class TreeColumnData extends AbstractData
{
    public ?Closure $valueResolver = null;

    public function __construct(
        public string $key,
        public string $label,
        public bool $hierarchy = false,
        public string $type = 'text',
        public ?string $field = null,
        public ?string $headerClass = null,
        public ?string $cellClass = null,
        public ?string $widthClass = null,
    ) {}

    public static function make(string $key, string $label): static
    {
        return new static($key, $label);
    }

    public function hierarchy(bool $hierarchy = true): static
    {
        $this->hierarchy = $hierarchy;

        return $this;
    }

    public function type(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function field(string $field): static
    {
        $this->field = $field;

        return $this;
    }

    public function colorBadge(?string $field = null): static
    {
        $this->type = 'color-badge';

        if ($field !== null) {
            $this->field = $field;
        }

        return $this;
    }

    public function valueUsing(Closure $resolver): static
    {
        $this->valueResolver = $resolver;

        return $this;
    }

    public function headerClass(string $headerClass): static
    {
        $this->headerClass = $headerClass;

        return $this;
    }

    public function cellClass(string $cellClass): static
    {
        $this->cellClass = $cellClass;

        return $this;
    }

    public function widthClass(string $widthClass): static
    {
        $this->widthClass = $widthClass;

        return $this;
    }

    public function resolveValue(TreeNodeData $item, mixed $component = null): mixed
    {
        if ($this->valueResolver !== null) {
            return ($this->valueResolver)($item, $component, $this);
        }

        return data_get($item->formData, $this->field ?? $this->key);
    }
}
