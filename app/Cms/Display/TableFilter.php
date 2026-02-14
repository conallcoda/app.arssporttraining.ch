<?php

namespace App\Cms\Display;

use App\Cms\Form\Field;
use Spatie\QueryBuilder\AllowedFilter;

class TableFilter
{
    protected string $name;

    protected AllowedFilter $allowedFilter;

    protected ?Field $field = null;

    public function __construct(string $name, AllowedFilter $allowedFilter)
    {
        $this->name = $name;
        $this->allowedFilter = $allowedFilter;
    }

    public static function exact(string $name, ?string $internalName = null): static
    {
        return new static($name, AllowedFilter::exact($name, $internalName));
    }

    public static function partial(string $name, ?string $internalName = null): static
    {
        return new static($name, AllowedFilter::partial($name, $internalName));
    }

    public static function scope(string $name, ?string $internalName = null): static
    {
        return new static($name, AllowedFilter::scope($name, $internalName));
    }

    public static function callback(string $name, callable $callback): static
    {
        return new static($name, AllowedFilter::callback($name, $callback));
    }

    public function field(Field $field): static
    {
        $this->field = $field;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAllowedFilter(): AllowedFilter
    {
        return $this->allowedFilter;
    }

    public function getField(): ?Field
    {
        return $this->field;
    }

    public function hasField(): bool
    {
        return $this->field !== null;
    }

    public function resolveDisplayValue(mixed $value): string
    {
        if ($this->field && method_exists($this->field, 'getOptions')) {
            $options = $this->field->getOptions();

            if (! empty($options)) {
                return $options[$value] ?? (string) $value;
            }
        }

        return (string) $value;
    }

    public function getLabel(): string
    {
        if ($this->field) {
            return $this->field->getLabel();
        }

        return str_replace('_', ' ', ucfirst($this->name));
    }
}
