<?php

namespace App\Cms\Form\Concerns;

use App\Cms\Form\Field;
use Spatie\LaravelOptions\Options;

trait HasOptions
{
    public array $options = [];

    public ?string $enumClass = null;

    public string $displayAttribute = 'name';

    public string $valueAttribute = 'id';

    protected ?\Closure $optionLoader = null;

    protected bool $optionsResolved = false;

    protected ?string $cacheKey = null;

    public function options(array $options): static
    {
        $this->options = $options;
        $this->optionsResolved = true;

        return $this;
    }

    public function getOptions(): array
    {
        if (! $this->optionsResolved && $this->optionLoader) {
            if ($this->cacheKey !== null) {
                $cached = Field::getCachedOptions($this->cacheKey);

                if ($cached !== null) {
                    $this->options = $cached;
                    $this->optionsResolved = true;

                    return $this->options;
                }
            }

            $this->options = ($this->optionLoader)();
            $this->optionsResolved = true;

            if ($this->cacheKey !== null) {
                Field::setCachedOptions($this->cacheKey, $this->options);
            }
        }

        return $this->options;
    }

    public function cached(?string $key = null): static
    {
        $this->cacheKey = $key;

        return $this;
    }

    public function enum(string $enumClass): static
    {
        $this->enumClass = $enumClass;

        $cases = $enumClass::cases();
        $hasLabels = method_exists($cases[0] ?? null, 'label');

        $this->options = $hasLabels
            ? collect($cases)->mapWithKeys(fn ($case) => [$case->value => $case->label()])->toArray()
            : collect(Options::forEnum($enumClass)->toArray())
                ->mapWithKeys(fn (array $option) => [$option['value'] => $option['label']])
                ->toArray();

        $this->optionsResolved = true;

        $values = array_map(fn ($case) => $case->value, $cases);

        $this->validationRules = 'required|string|in:'.implode(',', $values);
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
