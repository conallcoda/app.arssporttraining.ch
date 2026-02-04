<?php

namespace App\Form\Concerns;

use Spatie\LaravelOptions\Options;

trait HasOptions
{
    public array $options = [];

    public ?string $enumClass = null;

    public string $displayAttribute = 'name';

    public string $valueAttribute = 'id';

    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function enum(string $enumClass): static
    {
        $this->enumClass = $enumClass;

        $this->options = collect(Options::forEnum($enumClass)->toArray())
            ->mapWithKeys(fn(array $option) => [$option['value'] => $option['label']])
            ->toArray();

        $cases = $enumClass::cases();
        $values = array_map(fn($case) => $case->value, $cases);

        $this->validationRules = 'required|string|in:' . implode(',', $values);
        $this->default = $cases[0]->value ?? null;

        return $this;
    }

    public function displayAttribute(string $displayAttribute): static
    {
        $this->displayAttribute = $displayAttribute;

        return $this;
    }

    public function valueAttribute(string $valueAttribute): static
    {
        $this->valueAttribute = $valueAttribute;

        return $this;
    }
}
