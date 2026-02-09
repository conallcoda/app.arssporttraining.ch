<?php

namespace App\Cms\Form;

use Spatie\LaravelOptions\Options;

class TableColumn
{
    public function __construct(
        public string $field,
        public string $type = 'text',
        public ?string $label = null,
        public string $width = 'w-auto',
        public bool $editable = false,
        public ?string $suffix = null,
        public ?float $step = null,
        public ?float $min = null,
        public ?float $max = null,
        public string $displayAttribute = 'name',
        public ?string $modalField = null,
        public bool $sticky = false,
        public ?string $prefix = null,
        public ?string $enumClass = null,
        public bool $badge = false,
        public ?\Closure $source = null,
        public ?string $viewClass = null,
        public ?string $mask = null,
        public ?string $validationPattern = null,
    ) {}

    public static function id(): static
    {
        return (new static('id', 'text'))
            ->label('ID')
            ->width('w-16')
            ->prefix('#')
            ->sticky();
    }

    public static function text(string $field): static
    {
        return new static($field, 'text');
    }

    public static function number(string $field): static
    {
        return new static($field, 'number');
    }

    public static function tempo(string $field, bool $simple = false): static
    {
        $column = new static($field, 'text');

        if (! $simple) {
            $column->mask = '99-99-99-99';
            $column->validationPattern = '^\d{2}-\d{2}-\d{2}-\d{2}$';
        }

        return $column;
    }

    public static function percentage(string $field): static
    {
        return (new static($field, 'number'))
            ->min(0)
            ->max(999)
            ->step(1)
            ->suffix('%');
    }

    public static function relationship(string $field): static
    {
        return new static($field, 'relationship');
    }

    public static function view(string $field, string $viewClass): static
    {
        $column = new static($field, 'view');
        $column->viewClass = $viewClass;

        return $column;
    }

    public function getViewRouteName(): ?string
    {
        if (! $this->viewClass) {
            return null;
        }

        $className = class_basename($this->viewClass);

        return str($className)->kebab()->toString();
    }

    public function sticky(bool $sticky = true): static
    {
        $this->sticky = $sticky;

        return $this;
    }

    public function prefix(string $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function displayAttribute(string $attribute): static
    {
        $this->displayAttribute = $attribute;

        return $this;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function width(string $width): static
    {
        $this->width = $width;

        return $this;
    }

    public function inline(bool $inline = true): static
    {
        $this->editable = $inline;

        return $this;
    }

    public function suffix(string $suffix): static
    {
        $this->suffix = $suffix;

        return $this;
    }

    public function step(float $step): static
    {
        $this->step = $step;

        return $this;
    }

    public function min(float $min): static
    {
        $this->min = $min;

        return $this;
    }

    public function max(float $max): static
    {
        $this->max = $max;

        return $this;
    }

    public function modal(?string $fieldName = null): static
    {
        $this->modalField = $fieldName ?? $this->field;

        return $this;
    }

    public function getDisplayLabel(): string
    {
        return $this->label ?? ucfirst(str_replace('_', ' ', $this->field));
    }

    public function enum(string $enumClass): static
    {
        $this->enumClass = $enumClass;

        return $this;
    }

    public function badge(bool $badge = true): static
    {
        $this->badge = $badge;

        return $this;
    }

    public function source(\Closure $callback): static
    {
        $this->source = $callback;

        return $this;
    }

    public function getSourceData(mixed $item): array
    {
        if ($this->source === null) {
            return [];
        }

        return ($this->source)($item);
    }

    public function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($this->enumClass) {
            if ($value instanceof \BackedEnum && method_exists($value, 'label')) {
                return $value->label();
            }

            $enumValue = $value instanceof \BackedEnum ? $value->value : $value;

            if (! $value instanceof \BackedEnum) {
                $enum = $this->enumClass::tryFrom($enumValue);
                if ($enum && method_exists($enum, 'label')) {
                    return $enum->label();
                }
            }

            $options = collect(Options::forEnum($this->enumClass)->toArray())
                ->keyBy('value');

            return $options[$enumValue]['label'] ?? (string) $enumValue;
        }

        return (string) $value;
    }

    public function mask(string $mask): static
    {
        $this->mask = $mask;

        return $this;
    }

    public function validationPattern(string $pattern): static
    {
        $this->validationPattern = $pattern;

        return $this;
    }

    public function isValid(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if ($this->validationPattern === null) {
            return true;
        }

        return (bool) preg_match('/'.$this->validationPattern.'/', (string) $value);
    }
}
