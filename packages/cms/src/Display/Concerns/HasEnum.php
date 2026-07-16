<?php

namespace Coda\Cms\Display\Concerns;

use Spatie\LaravelOptions\Options;

trait HasEnum
{
    public ?string $enumClass = null;

    public function enum(string $enumClass): static
    {
        $this->enumClass = $enumClass;

        return $this;
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
}
