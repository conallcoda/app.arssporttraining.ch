<?php

namespace Coda\Cms\Livewire\Concerns;

use App\Models\Tag;
use Coda\Cms\Form\Field;
use Coda\Cms\Form\Fields\Relationship;
use Coda\Cms\Form\Fields\Repeater;
use Coda\Cms\Form\Fields\Tags;
use Coda\Cms\Form\FormFieldset;
use Coda\Cms\Form\FormFieldsetGroup;
use Illuminate\Support\Arr;

trait InteractsWithFormData
{
    public array $conditionalDataStash = [];

    /** @return FormFieldset[] */
    protected function flatFieldsets(): array
    {
        return collect($this->fieldsets)
            ->flatMap(fn ($item) => $this->flattenItem($item))
            ->all();
    }

    /** @return FormFieldset[] */
    protected function flattenItem(FormFieldset|FormFieldsetGroup $item): array
    {
        if ($item instanceof FormFieldset) {
            return [$item];
        }

        $result = [];

        if (! empty($item->headerFields)) {
            $result[] = FormFieldset::make('__header_'.$item->label)
                ->fields($item->headerFields)
                ->prefix($item->headerPrefix);
        }

        foreach ($item->fieldsets as $child) {
            array_push($result, ...$this->flattenItem($child));
        }

        return $result;
    }

    public function updated(string $property, mixed $value): void
    {
        $form = $this->formConfig;

        if ($form->hasDiscriminators()) {
            foreach ($form->getDiscriminators() as $field => $target) {
                if ($property === "data.{$field}") {
                    $this->resetDiscriminatorTarget($target, $value);
                    break;
                }
            }
        }

        if (str_starts_with($property, 'data.')) {
            unset($this->fieldsets);
            $this->syncConditionalFieldData();
            unset($this->fieldsets);
        }
    }

    protected function syncConditionalFieldData(): void
    {
        foreach ($this->flatFieldsets() as $fieldset) {
            $prefix = $this->getFieldsetDataPrefix($fieldset);

            foreach ($fieldset->hiddenFieldNames as $name) {
                $key = $prefix ? "{$prefix}.{$name}" : $name;
                $value = data_get($this->data, $key);

                if ($value !== null) {
                    data_set($this->conditionalDataStash, $key, $value);
                }

                Arr::forget($this->data, $key);
            }

            foreach ($fieldset->fields as $field) {
                $key = $prefix ? "{$prefix}.{$field->name}" : $field->name;
                $currentValue = data_get($this->data, $key);

                if ($currentValue !== null && method_exists($field, 'resolveDefault') && ! empty($field->defaultMap)) {
                    $siblingData = $prefix ? data_get($this->data, $prefix, []) : $this->data;
                    $resolvedDefault = $field->resolveDefault($siblingData);

                    if (in_array($currentValue, $field->defaultMap) && $currentValue != $resolvedDefault) {
                        data_set($this->data, $key, $resolvedDefault);
                    }
                }

                if ($currentValue !== null) {
                    continue;
                }

                $stashed = data_get($this->conditionalDataStash, $key);

                if ($stashed !== null) {
                    data_set($this->data, $key, $stashed);
                } elseif (method_exists($field, 'resolveDefault')) {
                    $siblingData = $prefix ? data_get($this->data, $prefix, []) : $this->data;
                    data_set($this->data, $key, $field->resolveDefault($siblingData));
                } elseif ($field->default !== null) {
                    data_set($this->data, $key, $field->default);
                }
            }
        }
    }

    protected function getFieldsetDataPrefix(FormFieldset $fieldset): ?string
    {
        if (! $fieldset->prefix || $fieldset->prefix === 'data') {
            return null;
        }

        return str_replace('data.', '', $fieldset->prefix);
    }

    protected function resetDiscriminatorTarget(string $target, mixed $discriminatorValue): void
    {
        $form = $this->formConfig;
        $fieldsets = $form->getFieldsets();

        foreach ($fieldsets as $name => $config) {
            if (! isset($config['resolver'])) {
                continue;
            }

            $resolved = ($config['resolver'])([$this->getDiscriminatorField($target) => $discriminatorValue]);

            if ($resolved === null) {
                $this->data[$target] = [];

                return;
            }

            $prefix = $resolved['prefix'] ?? null;
            if ($prefix && str_contains($prefix, $target)) {
                $this->data[$target] = Field::buildDefaults($resolved['fields']);

                return;
            }
        }

        $this->data[$target] = [];
    }

    protected function getDiscriminatorField(string $target): string
    {
        foreach ($this->formConfig->getDiscriminators() as $field => $t) {
            if ($t === $target) {
                return $field;
            }
        }

        return '';
    }

    protected function getAllFields(): array
    {
        return collect($this->flatFieldsets())
            ->flatMap(fn (FormFieldset $fs) => $fs->fields)
            ->all();
    }

    protected function buildValidationRulesFromFieldsets(): array
    {
        $rules = [];

        foreach ($this->flatFieldsets() as $fieldset) {
            $prefix = $fieldset->prefix ?? 'data';
            $fieldRules = Field::buildValidationRules($fieldset->fields, $prefix.'.');
            $rules = array_merge($rules, $fieldRules);
        }

        return $rules;
    }

    protected function buildDefaultsFromFieldsets(): array
    {
        $defaults = [];

        foreach ($this->flatFieldsets() as $fieldset) {
            $fieldDefaults = Field::buildDefaults($fieldset->fields);

            if ($fieldset->prefix && $fieldset->prefix !== 'data') {
                $nestedKey = str_replace('data.', '', $fieldset->prefix);
                data_set($defaults, $nestedKey, $fieldDefaults);
            } else {
                $defaults = array_merge($defaults, $fieldDefaults);
            }
        }

        return $defaults;
    }

    protected function ensureRelationshipItemsHaveKeys(): void
    {
        $relationshipFields = collect($this->getAllFields())
            ->filter(fn (Field $field) => $field instanceof Relationship)
            ->pluck('name')
            ->all();

        foreach ($relationshipFields as $fieldName) {
            if (! isset($this->data[$fieldName]) || ! is_array($this->data[$fieldName])) {
                continue;
            }

            foreach ($this->data[$fieldName] as $index => $item) {
                if (! isset($item['_key'])) {
                    $this->data[$fieldName][$index]['_key'] = uniqid('item_', true);
                }
            }
        }
    }

    public function addRelationshipItem(string $fieldName): void
    {
        if (! isset($this->data[$fieldName])) {
            $this->data[$fieldName] = [];
        }

        $field = collect($this->getAllFields())->firstWhere('name', $fieldName);
        $newItem = [
            $field?->valueAttribute ?? 'id' => null,
            '_key' => uniqid('item_', true),
        ];

        if ($field?->sortable) {
            $newItem['sort'] = count($this->data[$fieldName]);
        }

        $this->data[$fieldName][] = $newItem;
    }

    public function removeRelationshipItem(string $fieldName, int $index): void
    {
        if (! isset($this->data[$fieldName][$index])) {
            return;
        }

        unset($this->data[$fieldName][$index]);
        $this->data[$fieldName] = array_values($this->data[$fieldName]);

        $field = collect($this->getAllFields())->firstWhere('name', $fieldName);
        if ($field?->sortable) {
            foreach ($this->data[$fieldName] as $i => $item) {
                $this->data[$fieldName][$i]['sort'] = $i;
            }
        }
    }

    public function moveRelationshipItem(string $fieldName, int $index, int $direction): void
    {
        if (! isset($this->data[$fieldName])) {
            return;
        }

        $newIndex = $index + $direction;
        if ($newIndex < 0 || $newIndex >= count($this->data[$fieldName])) {
            return;
        }

        $items = $this->data[$fieldName];
        [$items[$index], $items[$newIndex]] = [$items[$newIndex], $items[$index]];

        $field = collect($this->getAllFields())->firstWhere('name', $fieldName);
        if ($field?->sortable) {
            foreach ($items as $i => $item) {
                $items[$i]['sort'] = $i;
            }
        }

        $this->data[$fieldName] = $items;
    }

    public function addRepeaterItem(string $fieldName): void
    {
        if (! isset($this->data[$fieldName])) {
            $this->data[$fieldName] = [];
        }

        $field = collect($this->getAllFields())->firstWhere('name', $fieldName);

        $newItem = [];
        if ($field instanceof Repeater && ! empty($field->schema)) {
            $newItem = Field::buildDefaults($field->schema);
        }

        $this->data[$fieldName][] = $newItem;
    }

    public function removeRepeaterItem(string $fieldName, int $index): void
    {
        if (! isset($this->data[$fieldName][$index])) {
            return;
        }

        unset($this->data[$fieldName][$index]);
        $this->data[$fieldName] = array_values($this->data[$fieldName]);
    }

    public function createTag(string $fieldName, string $name): void
    {
        $name = trim($name);

        if ($name === '') {
            return;
        }

        $field = collect($this->getAllFields())->firstWhere('name', $fieldName);

        if (! $field instanceof Tags) {
            return;
        }

        $tag = Tag::query()
            ->forScope($field->scope)
            ->where('name', $name)
            ->first();

        if (! $tag) {
            $tag = Tag::create([
                'name' => $name,
                'scope' => $field->scope,
            ]);
        }

        if (! isset($this->data[$fieldName])) {
            $this->data[$fieldName] = [];
        }

        if (! in_array($tag->id, $this->data[$fieldName])) {
            $this->data[$fieldName][] = $tag->id;
        }

        unset($this->fieldsets);
    }
}
