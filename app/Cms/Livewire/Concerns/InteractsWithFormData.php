<?php

namespace App\Cms\Livewire\Concerns;

use App\Cms\Form\Field;
use App\Cms\Form\Fields\Relationship;
use App\Cms\Form\Fields\Repeater;
use App\Cms\Form\FormFieldset;

trait InteractsWithFormData
{
    public function updated(string $property, mixed $value): void
    {
        $form = $this->formConfig;

        if (! $form->hasDiscriminators()) {
            return;
        }

        foreach ($form->getDiscriminators() as $field => $target) {
            if ($property === "data.{$field}") {
                $this->resetDiscriminatorTarget($target, $value);
                break;
            }
        }
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
        return collect($this->fieldsets)
            ->flatMap(fn (FormFieldset $fs) => $fs->fields)
            ->all();
    }

    protected function buildValidationRulesFromFieldsets(): array
    {
        $rules = [];

        foreach ($this->fieldsets as $fieldset) {
            $prefix = $fieldset->prefix ?? 'data';
            $fieldRules = Field::buildValidationRules($fieldset->fields, $prefix.'.');
            $rules = array_merge($rules, $fieldRules);
        }

        return $rules;
    }

    protected function buildDefaultsFromFieldsets(): array
    {
        $defaults = [];

        foreach ($this->fieldsets as $fieldset) {
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
}
