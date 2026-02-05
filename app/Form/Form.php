<?php

namespace App\Form;

use Closure;

class Form
{
    protected array $fields = [];

    protected array $fieldsets = [];

    protected array $discriminators = [];

    public static function make(): static
    {
        return new static;
    }

    public static function fields(array $fields): static
    {
        return (new static)->withFields($fields);
    }

    public function withFields(array $fields): static
    {
        $this->fields = $fields;

        return $this;
    }

    public function fieldset(string $name, string|Closure $labelOrResolver, ?array $fields = null, ?string $prefix = null): static
    {
        if ($labelOrResolver instanceof Closure) {
            $this->fieldsets[$name] = $labelOrResolver;
        } else {
            $this->fieldsets[$name] = [
                'label' => $labelOrResolver,
                'fields' => $fields ?? [],
                'prefix' => $prefix,
            ];
        }

        return $this;
    }

    public function discriminator(string $field, string $target): static
    {
        $this->discriminators[$field] = $target;

        return $this;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function getFieldsets(): array
    {
        return $this->fieldsets;
    }

    public function getDiscriminators(): array
    {
        return $this->discriminators;
    }

    public function hasFieldsets(): bool
    {
        return count($this->fieldsets) > 0;
    }

    public function hasDiscriminators(): bool
    {
        return count($this->discriminators) > 0;
    }
}
