<?php

namespace Coda\FormKit;

use Closure;
use Coda\FormKit\Support\Str;

class Form
{
    protected array $fields = [];

    protected array $fieldsets = [];

    protected array $fieldsetTabGroups = [];

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

    public function fieldset(string $label, array|Closure $fieldsOrResolver, ?string $prefix = null, ?string $show = null, ?string $view = null, array $viewData = [], ?array $layout = null, ?string $name = null): static
    {
        $key = $name ?? Str::snake($label);

        if ($fieldsOrResolver instanceof Closure) {
            $this->fieldsets[$key] = [
                'label' => $label,
                'resolver' => $fieldsOrResolver,
                'showExpression' => $show,
                'view' => $view,
                'viewData' => $viewData,
                'layout' => $layout,
            ];
        } else {
            $this->fieldsets[$key] = [
                'label' => $label,
                'fields' => $fieldsOrResolver,
                'prefix' => $prefix,
                'showExpression' => $show,
                'view' => $view,
                'viewData' => $viewData,
                'layout' => $layout,
            ];
        }

        return $this;
    }

    public function discriminator(string $field, string $target): static
    {
        $this->discriminators[$field] = $target;

        return $this;
    }

    public function fieldsetTabs(array $labels, ?string $label = null, ?string $sortByDataKey = null, ?array $headerFields = null, ?string $headerPrefix = null, bool $scrollableTabs = true): static
    {
        $this->fieldsetTabGroups[] = [
            'keys' => array_map(fn (string $l) => Str::snake($l), $labels),
            'label' => $label,
            'sortByDataKey' => $sortByDataKey,
            'headerFields' => $headerFields ?? [],
            'headerPrefix' => $headerPrefix,
            'appendKeys' => [],
            'scrollableTabs' => $scrollableTabs,
        ];

        return $this;
    }

    public function appendToFieldsetTabs(string $groupLabel, array $labels): static
    {
        $newKeys = array_map(fn (string $l) => Str::snake($l), $labels);

        foreach ($this->fieldsetTabGroups as &$group) {
            if ($group['label'] === $groupLabel) {
                $group['keys'] = array_merge($group['keys'], $newKeys);
                $group['appendKeys'] = array_merge($group['appendKeys'] ?? [], $newKeys);
                break;
            }
        }

        return $this;
    }

    public function getFields(): array
    {
        if (! empty($this->fields)) {
            return $this->fields;
        }

        $fields = [];

        foreach ($this->fieldsets as $config) {
            if (isset($config['resolver'])) {
                continue;
            }

            if (! empty($config['fields'])) {
                array_push($fields, ...$config['fields']);
            }
        }

        foreach ($this->fieldsetTabGroups as $group) {
            if (! empty($group['headerFields'])) {
                array_push($fields, ...$group['headerFields']);
            }
        }

        return $fields;
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

        $fieldsets = [];

        foreach ($this->fieldsets as $key => $config) {
            if (isset($config['resolver'])) {
                $resolved = ($config['resolver'])($data);

                if ($resolved === null) {
                    continue;
                }

                $fieldset = FormFieldset::make($key)
                    ->label($config['label'])
                    ->fields($resolved['fields'])
                    ->prefix($resolved['prefix'] ?? null)
                    ->layout($resolved['layout'] ?? ($config['layout'] ?? null));
            } else {
                $fieldset = FormFieldset::make($key)
                    ->label($config['label'])
                    ->fields($config['fields'])
                    ->prefix($config['prefix'] ?? null)
                    ->layout($config['layout'] ?? null);
            }

            if (! empty($config['view'])) {
                $fieldset->view($config['view']);
            }

            if (! empty($config['viewData'])) {
                $fieldset->viewData($config['viewData']);
            }

            if (! empty($config['showExpression'])) {
                $fieldset->show($config['showExpression']);
            }

            if ($fieldset->hasShowExpression() && ! $evaluator->evaluate($fieldset->showExpression, $data)) {
                continue;
            }

            $fieldData = $this->resolveFieldDataContext($data, $fieldset->prefix);
            $fieldDefaults = Field::buildDefaults($fieldset->fields);
            $fieldData = array_replace($fieldDefaults, $fieldData);
            $fieldset->contextData($fieldData);

            $fieldset->fields($this->applyLayoutFieldOverrides($fieldset->fields, $fieldset->layout));
            $allFieldNames = array_map(fn ($f) => $f->name, $fieldset->fields);
            $fieldset->fields($evaluator->filterFields($fieldset->fields, $fieldData));
            $visibleFieldNames = array_map(fn ($f) => $f->name, $fieldset->fields);
            $fieldset->hiddenFieldNames = array_values(array_diff($allFieldNames, $visibleFieldNames));
            $fieldset->rows($this->resolveFieldsetRows($fieldset, $fieldData, $evaluator));

            $fieldsets[] = $fieldset;
        }

        if (! empty($this->fieldsetTabGroups)) {
            $fieldsets = $this->applyTabGroups($fieldsets, $data);
        }

        return $fieldsets;
    }

    protected function applyTabGroups(array $fieldsets, array $data): array
    {
        foreach ($this->fieldsetTabGroups as $group) {
            $groupKeys = $group['keys'];
            $groupLabel = $group['label'] ?? null;
            $sortByDataKey = $group['sortByDataKey'] ?? null;
            $headerFields = $group['headerFields'] ?? [];
            $headerPrefix = $group['headerPrefix'] ?? null;
            $groupFieldsets = [];
            $insertIndex = null;

            foreach ($fieldsets as $index => $item) {
                $itemName = match (true) {
                    $item instanceof FormFieldset => $item->name,
                    $item instanceof FormFieldsetGroup => $item->name,
                    default => null,
                };

                if ($itemName !== null && in_array($itemName, $groupKeys)) {
                    $groupFieldsets[] = $item;
                    if ($insertIndex === null) {
                        $insertIndex = $index;
                    }
                }
            }

            if (empty($groupFieldsets) || $insertIndex === null) {
                continue;
            }

            $sortValues = $sortByDataKey ? Str::dataGet($data, $sortByDataKey) : null;
            if ($sortValues !== null && is_array($sortValues)) {
                $order = $sortValues;
                $appendKeys = $group['appendKeys'] ?? [];

                usort($groupFieldsets, function (FormFieldset|FormFieldsetGroup $a, FormFieldset|FormFieldsetGroup $b) use ($order, $appendKeys) {
                    $aIsAppended = in_array($a->name, $appendKeys);
                    $bIsAppended = in_array($b->name, $appendKeys);

                    if ($aIsAppended && ! $bIsAppended) {
                        return 1;
                    }
                    if (! $aIsAppended && $bIsAppended) {
                        return -1;
                    }
                    if ($aIsAppended && $bIsAppended) {
                        return 0;
                    }

                    $posA = array_search($a->name, $order);
                    $posB = array_search($b->name, $order);

                    if ($posA === false && $posB === false) {
                        return 0;
                    }
                    if ($posA === false) {
                        return -1;
                    }
                    if ($posB === false) {
                        return 1;
                    }

                    return $posA <=> $posB;
                });
            }

            $fieldsets = array_values(array_filter($fieldsets, function ($item) use ($groupKeys) {
                $itemName = match (true) {
                    $item instanceof FormFieldset => $item->name,
                    $item instanceof FormFieldsetGroup => $item->name,
                    default => null,
                };

                return $itemName === null || ! in_array($itemName, $groupKeys);
            }));

            $fieldsetGroup = FormFieldsetGroup::make($groupFieldsets, $groupLabel, $headerFields, $headerPrefix)
                ->scrollableTabs($group['scrollableTabs'] ?? true);

            array_splice($fieldsets, $insertIndex, 0, [$fieldsetGroup]);
        }

        return $fieldsets;
    }

    protected function resolveFieldDataContext(array $data, ?string $prefix): array
    {
        if ($prefix === null || $prefix === 'data') {
            return $data;
        }

        $nestedKey = str_replace('data.', '', $prefix);

        return Str::dataGet($data, $nestedKey) ?? [];
    }

    protected function applyLayoutFieldOverrides(array $fields, ?array $layout): array
    {
        $overrides = $this->collectLayoutFieldOverrides($layout);

        foreach ($fields as $field) {
            $config = $overrides[$field->name] ?? null;

            if (! is_array($config)) {
                continue;
            }

            $this->applyFieldOverrideConfig($field, $config);
        }

        return $fields;
    }

    protected function collectLayoutFieldOverrides(?array $layout): array
    {
        if (! is_array($layout) || $layout === []) {
            return [];
        }

        if ($this->isAssoc($layout)) {
            return collect($layout)
                ->filter(static fn ($config, $name) => is_string($name) && is_array($config))
                ->all();
        }

        $overrides = [];

        foreach ($layout as $row) {
            if (! is_array($row)) {
                continue;
            }

            foreach ($row['fields'] ?? [] as $item) {
                if (! is_array($item) || ! is_string($item['name'] ?? null)) {
                    continue;
                }

                $name = $item['name'];
                $overrides[$name] = array_merge($overrides[$name] ?? [], $item);
            }
        }

        return $overrides;
    }

    protected function applyFieldOverrideConfig(Field $field, array $config): void
    {
        foreach ($config as $key => $value) {
            if (in_array($key, ['name', 'span', 'class', 'fields', 'grid', 'when'], true)) {
                continue;
            }

            if ($key === 'configure' && $value instanceof Closure) {
                $value($field);

                continue;
            }

            if (! method_exists($field, $key)) {
                continue;
            }

            $field->{$key}($value);
        }
    }

    protected function resolveFieldsetRows(FormFieldset $fieldset, array $fieldData, ConditionEvaluator $evaluator): array
    {
        if ($fieldset->fields === []) {
            return [];
        }

        if (! is_array($fieldset->layout) || $fieldset->layout === []) {
            return $this->defaultFieldsetRows($fieldset->fields);
        }

        return $this->isAssoc($fieldset->layout)
            ? $this->resolvePartialFieldsetRows($fieldset->fields, $fieldset->layout)
            : $this->resolveExplicitFieldsetRows($fieldset->fields, $fieldset->layout, $fieldData, $evaluator);
    }

    protected function defaultFieldsetRows(array $fields): array
    {
        return array_map(
            fn (Field $field) => [
                'grid' => null,
                'fields' => [
                    [
                        'field' => $field,
                        'class' => '',
                    ],
                ],
            ],
            $fields,
        );
    }

    protected function resolvePartialFieldsetRows(array $fields, array $layout): array
    {
        $rows = [];
        $currentRow = [];
        $currentSpan = 0;

        foreach ($fields as $field) {
            $config = is_array($layout[$field->name] ?? null) ? $layout[$field->name] : [];
            $span = is_int($config['span'] ?? null) ? (int) $config['span'] : null;
            $class = trim((string) ($config['class'] ?? ''));

            if ($span === null) {
                if ($currentRow !== []) {
                    $rows[] = [
                        'grid' => 'md:grid-cols-12',
                        'fields' => $currentRow,
                    ];
                    $currentRow = [];
                    $currentSpan = 0;
                }

                $rows[] = [
                    'grid' => null,
                    'fields' => [
                        [
                            'field' => $field,
                            'class' => $class,
                        ],
                    ],
                ];

                continue;
            }

            $currentRow[] = [
                'field' => $field,
                'class' => trim("md:col-span-{$span} {$class}"),
            ];
            $currentSpan += $span;

            if ($currentSpan >= 12) {
                $rows[] = [
                    'grid' => 'md:grid-cols-12',
                    'fields' => $currentRow,
                ];
                $currentRow = [];
                $currentSpan = 0;
            }
        }

        if ($currentRow !== []) {
            $rows[] = [
                'grid' => 'md:grid-cols-12',
                'fields' => $currentRow,
            ];
        }

        return $rows;
    }

    protected function resolveExplicitFieldsetRows(array $fields, array $layout, array $fieldData, ConditionEvaluator $evaluator): array
    {
        $rows = [];
        $fieldsByName = collect($fields)->keyBy('name');
        $placed = [];

        foreach ($layout as $row) {
            if (! is_array($row)) {
                continue;
            }

            $when = $row['when'] ?? null;

            if (is_string($when) && ! $evaluator->evaluate($when, $fieldData)) {
                continue;
            }

            $items = [];

            foreach ($row['fields'] ?? [] as $item) {
                $item = is_string($item) ? ['name' => $item] : $item;

                if (! is_array($item) || ! is_string($item['name'] ?? null)) {
                    continue;
                }

                $itemWhen = $item['when'] ?? null;

                if (is_string($itemWhen) && ! $evaluator->evaluate($itemWhen, $fieldData)) {
                    continue;
                }

                $field = $fieldsByName->get($item['name']);

                if (! $field instanceof Field) {
                    continue;
                }

                $placed[] = $field->name;
                $class = trim((string) ($item['class'] ?? ''));
                $span = is_int($item['span'] ?? null) ? (int) $item['span'] : null;

                if ($span !== null) {
                    $class = trim("md:col-span-{$span} {$class}");
                }

                $items[] = [
                    'field' => $field,
                    'class' => $class,
                ];
            }

            if ($items === []) {
                continue;
            }

            $grid = $row['grid'] ?? null;
            $gridClass = is_int($grid) ? "md:grid-cols-{$grid}" : null;

            if ($gridClass === null && count($items) > 1 && collect($items)->contains(fn (array $item) => str_contains($item['class'], 'md:col-span-'))) {
                $gridClass = 'md:grid-cols-12';
            }

            $rows[] = [
                'grid' => $gridClass,
                'fields' => $items,
            ];
        }

        foreach ($fields as $field) {
            if (in_array($field->name, $placed, true)) {
                continue;
            }

            $rows[] = [
                'grid' => null,
                'fields' => [
                    [
                        'field' => $field,
                        'class' => '',
                    ],
                ],
            ];
        }

        return $rows;
    }

    protected function isAssoc(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }
}
