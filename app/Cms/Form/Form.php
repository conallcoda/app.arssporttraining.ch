<?php

namespace App\Cms\Form;

use Closure;
use Illuminate\Support\Str;

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
        return (new static)->setFields($fields);
    }

    public function setFields(array $fields): static
    {
        $this->fields = $fields;

        return $this;
    }

    public function fieldset(string $label, array|Closure $fieldsOrResolver, ?string $prefix = null): static
    {
        $key = Str::snake($label);

        if ($fieldsOrResolver instanceof Closure) {
            $this->fieldsets[$key] = [
                'label' => $label,
                'resolver' => $fieldsOrResolver,
            ];
        } else {
            $this->fieldsets[$key] = [
                'label' => $label,
                'fields' => $fieldsOrResolver,
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
        if (! empty($this->fields)) {
            return $this->fields;
        }

        return collect($this->fieldsets)
            ->reject(fn (array $config) => isset($config['resolver']))
            ->flatMap(fn (array $config) => $config['fields'] ?? [])
            ->values()
            ->all();
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

    public function resolveFieldsets(array $data = []): array
    {
        if (! $this->hasFieldsets()) {
            return [
                FormFieldset::make('general')
                    ->label('General')
                    ->fields($this->getFields()),
            ];
        }

        return collect($this->fieldsets)->map(function (array $config, string $key) use ($data) {
            if (isset($config['resolver'])) {
                $resolved = ($config['resolver'])($data);

                if ($resolved === null) {
                    return null;
                }

                return FormFieldset::make($key)
                    ->label($config['label'])
                    ->fields($resolved['fields'])
                    ->prefix($resolved['prefix'] ?? null);
            }

            return FormFieldset::make($key)
                ->label($config['label'])
                ->fields($config['fields'])
                ->prefix($config['prefix'] ?? null);
        })->filter()->values()->all();
    }
}
