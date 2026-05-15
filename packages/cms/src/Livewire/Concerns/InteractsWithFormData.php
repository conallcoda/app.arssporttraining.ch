<?php

namespace Coda\Cms\Livewire\Concerns;

use Coda\Cms\Form\Fields\Tags;
use Coda\FormKit\Field;
use Coda\FormKit\Fields\Relationship;
use Coda\FormKit\Fields\RelationshipSelector;
use Coda\FormKit\Fields\Repeater;
use Coda\FormKit\FormFieldset;
use Coda\FormKit\FormFieldsetGroup;
use Flux\Flux;
use Illuminate\Support\Arr;

trait InteractsWithFormData
{
    public array $conditionalDataStash = [];

    public array $relationshipSelectorSearch = [];

    public array $relationshipSelectorFilters = [];

    public array $relationshipSelectorSort = [];

    public array $relationshipSelectorTab = [];

    public array $relationshipSelectorDraftItems = [];

    public array $relationshipSelectorClientState = [];

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
            $this->syncConditionalFieldData($property);
            unset($this->fieldsets);
        }
    }

    protected function syncConditionalFieldData(?string $updatedProperty = null): void
    {
        $updatedKey = $updatedProperty !== null && str_starts_with($updatedProperty, 'data.')
            ? substr($updatedProperty, 5)
            : null;

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

                if ($key !== $updatedKey && $currentValue !== null && method_exists($field, 'resolveDefault') && ! empty($field->defaultMap)) {
                    $siblingData = $prefix ? (data_get($this->data, $prefix) ?? []) : $this->data;
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
                    $siblingData = $prefix ? (data_get($this->data, $prefix) ?? []) : $this->data;
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
            $fieldRules = Field::buildValidationRules($fieldset->fields, $prefix.'.', $this->data);
            $rules = array_merge($rules, $fieldRules);
        }

        return $rules;
    }

    protected function buildValidationAttributesFromFieldsets(): array
    {
        $attributes = [];

        foreach ($this->flatFieldsets() as $fieldset) {
            $prefix = $fieldset->prefix ?? 'data';
            $fieldAttributes = Field::buildValidationAttributes($fieldset->fields, $prefix.'.');
            $attributes = array_merge($attributes, $fieldAttributes);
        }

        return $attributes;
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
            ->filter(fn (Field $field) => $field instanceof Relationship || $field instanceof RelationshipSelector)
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

    public function openRelationshipSelector(string $fieldName): void
    {
        $field = $this->findField($fieldName);

        if (! $field instanceof RelationshipSelector) {
            return;
        }

        if (! isset($this->relationshipSelectorFilters[$fieldName]) || ! is_array($this->relationshipSelectorFilters[$fieldName])) {
            $this->relationshipSelectorFilters[$fieldName] = Field::buildDefaults($field->filterFields);
        }

        if (! isset($this->relationshipSelectorSort[$fieldName]) || ! is_array($this->relationshipSelectorSort[$fieldName])) {
            $this->relationshipSelectorSort[$fieldName] = [
                'field' => $field->defaultSortField,
                'direction' => $field->defaultSortDirection,
            ];
        }

        $this->relationshipSelectorTab[$fieldName] = $this->relationshipSelectorTab[$fieldName] ?? 'results';
        $this->relationshipSelectorSearch[$fieldName] = '';

        if ($field->deferModalApply) {
            $this->relationshipSelectorDraftItems[$fieldName] = $this->cloneRelationshipSelectorItems($this->data[$fieldName] ?? []);
        }

        Flux::modal($this->relationshipSelectorModalName($fieldName))->show();
    }

    public function relationshipSelectorClientInitialState(string $fieldName, int $limit = 40): array
    {
        $field = $this->findField($fieldName);

        if (! $field instanceof RelationshipSelector || ! $field->clientModal) {
            return [];
        }

        $selectedItems = $this->cloneRelationshipSelectorItems($this->data[$fieldName] ?? []);
        $selectedIds = collect($selectedItems)
            ->pluck($field->valueAttribute)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => (string) $value)
            ->values()
            ->all();
        $filters = [];
        $sort = [
            'field' => $field->defaultSortField,
            'direction' => $field->defaultSortDirection,
        ];

        return [
            'selectedItems' => $this->buildRelationshipSelectorClientSelectedItems($field, $selectedItems, $selectedIds, $filters),
            'results' => $this->buildRelationshipSelectorClientResults($field, '', $selectedIds, $filters, $selectedItems, $sort, 0, $limit),
            'schemaDefaults' => Field::buildDefaults($field->schema),
            'initialListKey' => $this->relationshipSelectorClientInitialListKey($field, $selectedItems),
            'limit' => $limit,
        ];
    }

    public function relationshipSelectorClientPage(
        string $fieldName,
        string $query = '',
        int $offset = 0,
        int $limit = 40,
        array $selectedItems = [],
    ): array {
        $field = $this->findField($fieldName);

        if (! $field instanceof RelationshipSelector || ! $field->clientModal) {
            return ['records' => [], 'nextOffset' => 0, 'hasMore' => false];
        }

        $items = $selectedItems !== [] ? $this->cloneRelationshipSelectorItems($selectedItems) : $this->cloneRelationshipSelectorItems($this->data[$fieldName] ?? []);
        $selectedIds = collect($items)
            ->pluck($field->valueAttribute)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => (string) $value)
            ->values()
            ->all();

        return $this->buildRelationshipSelectorClientResults(
            $field,
            $query,
            $selectedIds,
            [],
            $items,
            [
                'field' => $field->defaultSortField,
                'direction' => $field->defaultSortDirection,
            ],
            $offset,
            $limit,
        );
    }

    public function sortRelationshipSelectorBy(string $fieldName, string $sortField): void
    {
        $field = $this->findField($fieldName);

        if (! $field instanceof RelationshipSelector) {
            return;
        }

        $currentField = $this->relationshipSelectorSort[$fieldName]['field'] ?? null;
        $currentDirection = $this->relationshipSelectorSort[$fieldName]['direction'] ?? 'asc';

        $this->relationshipSelectorSort[$fieldName] = [
            'field' => $sortField,
            'direction' => $currentField === $sortField && $currentDirection === 'asc' ? 'desc' : 'asc',
        ];
    }

    public function toggleRelationshipSelectorFilterOption(string $fieldName, string $filterName, mixed $value): void
    {
        $selected = collect($this->relationshipSelectorFilters[$fieldName][$filterName] ?? [])
            ->filter(fn ($item) => $item !== null && $item !== '')
            ->map(fn ($item) => (string) $item)
            ->values()
            ->all();

        $value = (string) $value;

        if (in_array($value, $selected, true)) {
            $selected = array_values(array_filter($selected, fn ($item) => $item !== $value));
        } else {
            $selected[] = $value;
        }

        $this->relationshipSelectorFilters[$fieldName][$filterName] = $selected;
    }

    public function toggleRelationshipSelectorItem(string $fieldName, mixed $value): void
    {
        $field = $this->findField($fieldName);

        if (! $field instanceof RelationshipSelector) {
            return;
        }

        if (! isset($this->data[$fieldName]) || ! is_array($this->data[$fieldName])) {
            $this->data[$fieldName] = [];
        }

        $existingIndex = collect($this->data[$fieldName])
            ->search(fn (mixed $item) => (string) ($item[$field->valueAttribute] ?? '') === (string) $value);

        if ($existingIndex !== false) {
            unset($this->data[$fieldName][$existingIndex]);
            $this->data[$fieldName] = array_values($this->data[$fieldName]);
            $this->normalizeRelationshipSelectorSort($fieldName, $field);

            return;
        }

        $newItem = array_merge(
            Field::buildDefaults($field->schema),
            [
                $field->valueAttribute => $value,
                '_key' => uniqid('item_', true),
            ],
        );

        if (! $field->multiple) {
            $this->data[$fieldName] = [$newItem];
            $this->normalizeRelationshipSelectorSort($fieldName, $field);
            Flux::modal($this->relationshipSelectorModalName($fieldName))->close();

            return;
        }

        if ($field->sortable) {
            $newItem['sort'] = count($this->data[$fieldName]);
        }

        $this->data[$fieldName][] = $newItem;
    }

    public function toggleRelationshipSelectorDraftItem(string $fieldName, mixed $value): void
    {
        $field = $this->findField($fieldName);

        if (! $field instanceof RelationshipSelector || ! $field->deferModalApply) {
            return;
        }

        if (! isset($this->relationshipSelectorDraftItems[$fieldName]) || ! is_array($this->relationshipSelectorDraftItems[$fieldName])) {
            $this->relationshipSelectorDraftItems[$fieldName] = $this->cloneRelationshipSelectorItems($this->data[$fieldName] ?? []);
        }

        $existingIndex = collect($this->relationshipSelectorDraftItems[$fieldName])
            ->search(fn (mixed $item) => (string) ($item[$field->valueAttribute] ?? '') === (string) $value);

        if ($existingIndex !== false) {
            unset($this->relationshipSelectorDraftItems[$fieldName][$existingIndex]);
            $this->relationshipSelectorDraftItems[$fieldName] = array_values($this->relationshipSelectorDraftItems[$fieldName]);
            $this->normalizeRelationshipSelectorDraftSort($fieldName, $field);

            return;
        }

        $newItem = array_merge(
            Field::buildDefaults($field->schema),
            [
                $field->valueAttribute => $value,
                '_key' => uniqid('item_', true),
            ],
        );

        if (! $field->multiple) {
            $this->relationshipSelectorDraftItems[$fieldName] = [$newItem];
            $this->normalizeRelationshipSelectorDraftSort($fieldName, $field);

            return;
        }

        if ($field->sortable) {
            $newItem['sort'] = count($this->relationshipSelectorDraftItems[$fieldName]);
        }

        $this->relationshipSelectorDraftItems[$fieldName][] = $newItem;
    }

    public function removeRelationshipSelectorItem(string $fieldName, int $index): void
    {
        if (! isset($this->data[$fieldName][$index])) {
            return;
        }

        unset($this->data[$fieldName][$index]);
        $this->data[$fieldName] = array_values($this->data[$fieldName]);

        $field = $this->findField($fieldName);

        if ($field instanceof RelationshipSelector) {
            $this->normalizeRelationshipSelectorSort($fieldName, $field);
        }
    }

    public function removeRelationshipSelectorDraftItem(string $fieldName, int $index): void
    {
        $field = $this->findField($fieldName);

        if (! $field instanceof RelationshipSelector || ! $field->deferModalApply || ! isset($this->relationshipSelectorDraftItems[$fieldName][$index])) {
            return;
        }

        unset($this->relationshipSelectorDraftItems[$fieldName][$index]);
        $this->relationshipSelectorDraftItems[$fieldName] = array_values($this->relationshipSelectorDraftItems[$fieldName]);
        $this->normalizeRelationshipSelectorDraftSort($fieldName, $field);
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

    public function reorderRelationshipItem(string $fieldName, int $sourceIndex, int $targetIndex): void
    {
        if (! isset($this->data[$fieldName])) {
            return;
        }

        $items = $this->data[$fieldName];

        if ($sourceIndex < 0 || $sourceIndex >= count($items) || $targetIndex < 0 || $targetIndex >= count($items)) {
            return;
        }

        $moved = array_splice($items, $sourceIndex, 1);
        array_splice($items, $targetIndex, 0, $moved);

        $field = collect($this->getAllFields())->firstWhere('name', $fieldName);
        if ($field?->sortable) {
            foreach ($items as $i => $item) {
                $items[$i]['sort'] = $i;
            }
        }

        $this->data[$fieldName] = $items;
    }

    public function reorderRelationshipSelectorDraftItem(string $fieldName, int $sourceIndex, int $targetIndex): void
    {
        $field = $this->findField($fieldName);

        if (! $field instanceof RelationshipSelector || ! $field->deferModalApply) {
            return;
        }

        $items = $this->relationshipSelectorDraftItems[$fieldName] ?? null;

        if (! is_array($items) || $sourceIndex < 0 || $sourceIndex >= count($items) || $targetIndex < 0 || $targetIndex >= count($items)) {
            return;
        }

        $moved = array_splice($items, $sourceIndex, 1);
        array_splice($items, $targetIndex, 0, $moved);

        $this->relationshipSelectorDraftItems[$fieldName] = array_values($items);
        $this->normalizeRelationshipSelectorDraftSort($fieldName, $field);
    }

    public function applyRelationshipSelectorDraft(string $fieldName, array $orderedKeys = []): void
    {
        $field = $this->findField($fieldName);

        if (! $field instanceof RelationshipSelector || ! $field->deferModalApply) {
            return;
        }

        if ($field->sortable && ! empty($orderedKeys) && isset($this->relationshipSelectorDraftItems[$fieldName]) && is_array($this->relationshipSelectorDraftItems[$fieldName])) {
            $itemsByKey = collect($this->relationshipSelectorDraftItems[$fieldName])
                ->filter(fn (mixed $item) => isset($item['_key']))
                ->keyBy(fn (array $item) => (string) $item['_key']);

            $orderedItems = collect($orderedKeys)
                ->map(fn (mixed $key) => $itemsByKey->get((string) $key))
                ->filter()
                ->values();

            $remainingItems = collect($this->relationshipSelectorDraftItems[$fieldName])
                ->reject(fn (mixed $item) => in_array((string) ($item['_key'] ?? ''), $orderedKeys, true))
                ->values();

            $this->relationshipSelectorDraftItems[$fieldName] = $orderedItems
                ->concat($remainingItems)
                ->values()
                ->all();

            $this->normalizeRelationshipSelectorDraftSort($fieldName, $field);
        }

        $this->data[$fieldName] = $this->cloneRelationshipSelectorItems($this->relationshipSelectorDraftItems[$fieldName] ?? []);
        $this->normalizeRelationshipSelectorSort($fieldName, $field);

        unset($this->relationshipSelectorDraftItems[$fieldName]);

        Flux::modal($this->relationshipSelectorModalName($fieldName))->close();
    }

    public function applyRelationshipSelectorClientState(string $fieldName, array $items = []): void
    {
        $field = $this->findField($fieldName);

        if (! $field instanceof RelationshipSelector || ! $field->clientModal) {
            return;
        }

        $normalizedItems = collect($items)
            ->filter(fn (mixed $item) => is_array($item) && ($item[$field->valueAttribute] ?? null) !== null)
            ->values()
            ->map(function (array $item, int $index) use ($field): array {
                $item['_key'] = $item['_key'] ?? uniqid('item_', true);

                if ($field->sortable) {
                    $item['sort'] = $index;
                }

                return $item;
            })
            ->all();

        $this->data[$fieldName] = $normalizedItems;
        $this->normalizeRelationshipSelectorSort($fieldName, $field);

        Flux::modal($this->relationshipSelectorModalName($fieldName))->close();
    }

    public function cancelRelationshipSelectorDraft(string $fieldName): void
    {
        $field = $this->findField($fieldName);

        if (! $field instanceof RelationshipSelector || ! $field->deferModalApply) {
            return;
        }

        unset($this->relationshipSelectorDraftItems[$fieldName]);

        Flux::modal($this->relationshipSelectorModalName($fieldName))->close();
    }

    protected function normalizeRelationshipSelectorSort(string $fieldName, RelationshipSelector $field): void
    {
        if (! $field->sortable || ! isset($this->data[$fieldName]) || ! is_array($this->data[$fieldName])) {
            return;
        }

        foreach ($this->data[$fieldName] as $index => $item) {
            $this->data[$fieldName][$index]['sort'] = $index;
        }
    }

    protected function normalizeRelationshipSelectorDraftSort(string $fieldName, RelationshipSelector $field): void
    {
        if (! $field->sortable || ! isset($this->relationshipSelectorDraftItems[$fieldName]) || ! is_array($this->relationshipSelectorDraftItems[$fieldName])) {
            return;
        }

        foreach ($this->relationshipSelectorDraftItems[$fieldName] as $index => $item) {
            $this->relationshipSelectorDraftItems[$fieldName][$index]['sort'] = $index;
        }
    }

    protected function cloneRelationshipSelectorItems(array $items): array
    {
        return json_decode(json_encode($items), true) ?? [];
    }

    protected function relationshipSelectorClientInitialListKey(RelationshipSelector $field, array $selectedItems): string
    {
        $lists = method_exists($field, 'getClientModalListPayload')
            ? $field->getClientModalListPayload()
            : [
                ['key' => 'results', 'rows' => 'resultRows'],
                ['key' => 'selected', 'rows' => 'selectedRows'],
            ];

        $firstListKey = (string) ($lists[0]['key'] ?? 'results');

        if ($selectedItems === []) {
            return $firstListKey;
        }

        $selectedList = collect($lists)->first(fn (array $list): bool => ($list['rows'] ?? null) === 'selectedRows');

        return (string) ($selectedList['key'] ?? $firstListKey);
    }

    protected function buildRelationshipSelectorClientSelectedItems(
        RelationshipSelector $field,
        array $selectedItems,
        array $selectedIds,
        array $filters = [],
    ): array {
        $selectedRecords = collect($field->getSelectedRecords($selectedIds, $filters, $selectedItems))
            ->mapWithKeys(function ($record) use ($field) {
                $key = $field->resolveRecordKey($record);

                return $key === null ? [] : [(string) $key => $record];
            });

        return collect($selectedItems)
            ->map(function (array $item) use ($field, $selectedRecords): ?array {
                $value = $item[$field->valueAttribute] ?? null;

                if ($value === null || $value === '') {
                    return null;
                }

                $record = $selectedRecords->get((string) $value);

                return [
                    'item' => $item,
                    'record' => $record
                        ? $field->serializeRecordForClientModal($record)
                        : [
                            'key' => $value,
                            'label' => (string) $value,
                            'columns' => [[
                                'type' => 'text',
                                'text' => (string) $value,
                            ]],
                        ],
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function buildRelationshipSelectorClientResults(
        RelationshipSelector $field,
        string $query,
        array $selectedIds,
        array $filters,
        array $selectedItems,
        array $sort,
        int $offset,
        int $limit,
    ): array {
        $fetchLimit = max(1, $limit) + 1;
        $records = collect($field->getSearchResults(
            $query,
            $selectedIds,
            [],
            $filters,
            $selectedItems,
            $sort,
            max(0, $offset),
            $fetchLimit,
        ));

        $hasMore = $records->count() > max(1, $limit);
        $pageRecords = $records->take(max(1, $limit))->values();

        return [
            'records' => $pageRecords
                ->map(fn ($record) => $field->serializeRecordForClientModal($record))
                ->values()
                ->all(),
            'nextOffset' => max(0, $offset) + $pageRecords->count(),
            'hasMore' => $hasMore,
        ];
    }

    protected function findField(string $fieldName): ?Field
    {
        return collect($this->getAllFields())->firstWhere('name', $fieldName);
    }

    public function relationshipSelectorModalName(string $fieldName): string
    {
        return 'relationship-selector-'.md5($fieldName);
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

        $tagModel = config('cms.models.tag');

        $tag = $tagModel::query()
            ->forScope($field->scope)
            ->where('name', $name)
            ->first();

        if (! $tag) {
            $tag = $tagModel::create([
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
