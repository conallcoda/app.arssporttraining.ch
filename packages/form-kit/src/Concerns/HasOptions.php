<?php

namespace Coda\FormKit\Concerns;

use Coda\FormKit\Field;
use ReflectionFunction;

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

    public function optionsUsing(\Closure $loader): static
    {
        $this->optionLoader = $loader;
        $this->optionsResolved = false;
        $this->options = [];

        return $this;
    }

    public function getOptions(array $context = []): array
    {
        if (! $this->optionLoader) {
            return $this->options;
        }

        $cacheKey = $this->cacheKey;

        if ($cacheKey !== null && $context !== []) {
            $cacheKey .= ':'.md5(json_encode($context));
        }

        if ($cacheKey !== null) {
            $cached = Field::getCachedOptions($cacheKey);

            if ($cached !== null) {
                return $cached;
            }
        }

        $reflection = new ReflectionFunction($this->optionLoader);

        $options = $reflection->getNumberOfParameters() > 0
            ? ($this->optionLoader)($context)
            : ($this->optionLoader)();

        if ($cacheKey !== null) {
            Field::setCachedOptions($cacheKey, $options);
        }

        return $options;
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
