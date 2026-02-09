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

    public function fieldset(string $label, array|Closure $fieldsOrResolver, ?string $prefix = null, ?string $show = null): static
    {
        $key = Str::snake($label);

        if ($fieldsOrResolver instanceof Closure) {
            $this->fieldsets[$key] = [
                'label' => $label,
                'resolver' => $fieldsOrResolver,
                'showExpression' => $show,
            ];
        } else {
            $this->fieldsets[$key] = [
                'label' => $label,
                'fields' => $fieldsOrResolver,
                'prefix' => $prefix,
                'showExpression' => $show,
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
        $evaluator = new ConditionEvaluator;

        if (! $this->hasFieldsets()) {
            return [
                FormFieldset::make('general')
                    ->label('General')
                    ->fields($evaluator->filterFields($this->getFields(), $data)),
            ];
        }

        return collect($this->fieldsets)->map(function (array $config, string $key) use ($data, $evaluator) {
            if (isset($config['resolver'])) {
                $resolved = ($config['resolver'])($data);

                if ($resolved === null) {
                    return null;
                }

                $fieldset = FormFieldset::make($key)
                    ->label($config['label'])
                    ->fields($resolved['fields'])
                    ->prefix($resolved['prefix'] ?? null);
            } else {
                $fieldset = FormFieldset::make($key)
                    ->label($config['label'])
                    ->fields($config['fields'])
                    ->prefix($config['prefix'] ?? null);
            }

            if (! empty($config['showExpression'])) {
                $fieldset->show($config['showExpression']);
            }

            if ($fieldset->hasShowExpression() && ! $evaluator->evaluate($fieldset->showExpression, $data)) {
                return null;
            }

            $fieldData = $this->resolveFieldDataContext($data, $fieldset->prefix);
            $fieldDefaults = Field::buildDefaults($fieldset->fields);
            $fieldData = array_replace($fieldDefaults, $fieldData);

            $allFieldNames = collect($fieldset->fields)->pluck('name')->all();
            $fieldset->fields($evaluator->filterFields($fieldset->fields, $fieldData));
            $visibleFieldNames = collect($fieldset->fields)->pluck('name')->all();
            $fieldset->hiddenFieldNames = array_values(array_diff($allFieldNames, $visibleFieldNames));

            return $fieldset;
        })->filter()->values()->all();
    }

    protected function resolveFieldDataContext(array $data, ?string $prefix): array
    {
        if ($prefix === null || $prefix === 'data') {
            return $data;
        }

        $nestedKey = str_replace('data.', '', $prefix);

        return data_get($data, $nestedKey, []);
    }
}
