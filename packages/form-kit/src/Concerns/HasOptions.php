<?php

namespace Coda\FormKit\Concerns;

use Coda\FormKit\Field;

trait HasOptions
{
    public array $options = [];

    public ?string $enumClass = null;

    public string $displayAttribute = 'name';

    public string $valueAttribute = 'id';

    protected ?\Closure $optionLoader = null;

    protected bool $optionsResolved = false;

    public ?string $optionView = null;

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

        $options = [];

        if ($hasLabels) {
            foreach ($cases as $case) {
                $options[$case->value] = $case->label();
            }
        } else {
            foreach ($cases as $case) {
                $options[$case->value] = ucwords(preg_replace('/(?<!^)([A-Z])/', ' $1', str_replace('_', ' ', $case->name)));
            }
        }

        $this->options = $options;
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

    public function optionView(string $view): static
    {
        $this->optionView = $view;

        return $this;
    }
}
