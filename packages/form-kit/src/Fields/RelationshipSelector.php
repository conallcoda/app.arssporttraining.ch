<?php

namespace Coda\FormKit\Fields;

use Closure;
use Coda\Cms\Display\DisplayField;
use Coda\Cms\Display\DisplayFields\Badge as BadgeColumn;
use Coda\Cms\Display\DisplayFields\CompactDisplay as CompactDisplayColumn;
use Coda\Cms\Display\DisplayFields\ColorBadge as ColorBadgeColumn;
use Coda\Cms\Display\DisplayFields\Text as TextColumn;
use Coda\Cms\Support\ColorPalette;
use Coda\FormKit\Concerns\HasOptions;
use Coda\FormKit\Concerns\HasPlaceholder;
use Coda\FormKit\Concerns\HasSchema;
use Coda\FormKit\Concerns\HasSortable;
use Coda\FormKit\Field;
use stdClass;

class RelationshipSelector extends Field
{
    use HasOptions;
    use HasPlaceholder;
    use HasSchema;
    use HasSortable;

    public string $type = 'relationship-selector';

    public bool $multiple = true;

    public ?Closure $searchCallback = null;

    public ?Closure $selectedRecordsCallback = null;

    public ?string $resultView = null;

    public ?string $selectionView = null;

    /** @var array<int, Field> */
    public array $filterFields = [];

    public array $resultColumns = [];

    public array $buttonFilters = [];

    public ?string $defaultSortField = null;

    public string $defaultSortDirection = 'asc';

    public string $searchPlaceholder = 'Search...';

    public string $selectButtonLabel = 'Select';

    public ?string $triggerButtonLabel = null;

    public string $triggerButtonIcon = 'plus';

    public ?string $modalTitle = null;

    public bool $deferModalApply = false;

    public bool $clientModal = false;

    public string $emptySelectionText = 'No items selected yet.';

    public string $inlineSelectionDisplay = 'default';

    public bool $showInlineSchema = true;

    public ?string $clientModalSaveAction = null;

    public ?string $clientModalInitialListKey = null;

    /** @var array<int, array<string, mixed>> */
    public array $clientModalLists = [];

    /** @var array<int, array<string, mixed>> */
    public array $clientModalStateFields = [];

    /** @var array<string, mixed> */
    public array $clientModalStateDefaults = [];

    public function searchable(Closure $callback): static
    {
        $this->searchCallback = $callback;

        return $this;
    }

    public function selectedRecordsUsing(Closure $callback): static
    {
        $this->selectedRecordsCallback = $callback;

        return $this;
    }

    public function resultView(string $view): static
    {
        $this->resultView = $view;

        return $this;
    }

    public function optionView(string $view): static
    {
        return $this->resultView($view);
    }

    public function selectionView(string $view): static
    {
        $this->selectionView = $view;

        return $this;
    }

    public function filters(array $fields): static
    {
        $this->filterFields = $fields;

        return $this;
    }

    public function columns(array $columns): static
    {
        $this->resultColumns = $columns;

        return $this;
    }

    public function buttonFilters(array $filters): static
    {
        $this->buttonFilters = $filters;

        return $this;
    }

    public function defaultSort(string $field, string $direction = 'asc'): static
    {
        $this->defaultSortField = $field;
        $this->defaultSortDirection = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        return $this;
    }

    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    public function single(bool $single = true): static
    {
        $this->multiple = ! $single;

        return $this;
    }

    public function selectButtonLabel(string $label): static
    {
        $this->selectButtonLabel = $label;

        return $this;
    }

    public function modalTitle(string $title): static
    {
        $this->modalTitle = $title;

        return $this;
    }

    public function deferModalApply(bool $defer = true): static
    {
        $this->deferModalApply = $defer;

        return $this;
    }

    public function clientModal(bool $clientModal = true): static
    {
        $this->clientModal = $clientModal;

        return $this;
    }

    public function emptySelectionText(string $text): static
    {
        $this->emptySelectionText = $text;

        return $this;
    }

    public function triggerButtonLabel(string $label): static
    {
        $this->triggerButtonLabel = $label;

        return $this;
    }

    public function triggerButtonIcon(string $icon): static
    {
        $this->triggerButtonIcon = $icon;

        return $this;
    }

    public function inlineSelectionDisplay(string $display): static
    {
        $this->inlineSelectionDisplay = $display;

        return $this;
    }

    public function showInlineSchema(bool $show = true): static
    {
        $this->showInlineSchema = $show;

        return $this;
    }

    public function clientModalSaveAction(string $action): static
    {
        $this->clientModalSaveAction = $action;

        return $this;
    }

    public function clientModalInitialListKey(string $listKey): static
    {
        $this->clientModalInitialListKey = $listKey;

        return $this;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lists
     */
    public function clientModalLists(array $lists): static
    {
        $this->clientModalLists = array_values($lists);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $list
     */
    public function addClientModalList(array $list): static
    {
        $this->clientModalLists[] = $list;

        return $this;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     */
    public function clientModalStateFields(array $fields): static
    {
        $this->clientModalStateFields = array_values($fields);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $defaults
     */
    public function clientModalStateDefaults(array $defaults): static
    {
        $this->clientModalStateDefaults = $defaults;

        return $this;
    }

    public function searchPlaceholder(string $placeholder): static
    {
        $this->searchPlaceholder = $placeholder;

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getClientModalLists(): array
    {
        $lists = $this->clientModalLists !== [] ? $this->clientModalLists : $this->defaultClientModalLists();

        return array_values(array_map(
            fn (array $list, int $index): array => $this->normalizeClientModalList($list, $index),
            $lists,
            array_keys($lists),
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getClientModalListPayload(): array
    {
        return collect($this->getClientModalLists())
            ->map(fn (array $list): array => [
                'key' => $list['key'],
                'label' => $list['label'],
                'rows' => $list['rows'],
                'loader' => $list['loader'],
                'searchable' => $list['searchable'],
                'searchPlaceholder' => $list['searchPlaceholder'],
                'rowAction' => $list['rowAction'],
                'sortable' => $list['sortable'],
                'sortKey' => $list['sortKey'],
                'selectedState' => $list['selectedState'],
                'emptyText' => $list['emptyText'],
                'button' => $list['button'],
                'badge' => $list['badge'],
                'saveButton' => $list['saveButton'],
                'itemFields' => $list['itemFields'],
                'panelFields' => $list['panelFields'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function getClientModalStateDefaults(): array
    {
        return $this->clientModalStateDefaults;
    }

    /**
     * @param  array<int|string>  $selectedIds
     * @param  array<int|string>  $excludedIds
     * @param  array<string, mixed>  $filters
     * @param  array<int, array<string, mixed>>  $items
     * @param  array{field?: ?string, direction?: ?string}  $sort
     * @return iterable<mixed>
     */
    public function getSearchResults(
        ?string $query,
        array $selectedIds = [],
        array $excludedIds = [],
        array $filters = [],
        array $items = [],
        array $sort = [],
        int $offset = 0,
        int $limit = 40,
    ): iterable {
        if ($this->searchCallback === null) {
            return $this->fallbackSearchResults($query);
        }

        return $this->invokeCallback(
            $this->searchCallback,
            [(string) ($query ?? ''), $selectedIds, $excludedIds, $filters, $items, $sort, $offset, $limit],
        );
    }

    /**
     * @param  array<int|string>  $selectedIds
     * @param  array<string, mixed>  $filters
     * @param  array<int, array<string, mixed>>  $items
     * @return iterable<mixed>
     */
    public function getSelectedRecords(array $selectedIds, array $filters = [], array $items = []): iterable
    {
        if ($this->selectedRecordsCallback === null) {
            return $this->fallbackSelectedRecords($selectedIds);
        }

        return $this->invokeCallback(
            $this->selectedRecordsCallback,
            [$selectedIds, $filters, $items],
        );
    }

    public function resolveRecordKey(mixed $record): string|int|null
    {
        $valueAttribute = $this->valueAttribute;

        return match (true) {
            is_array($record) => $record[$valueAttribute] ?? $record['id'] ?? null,
            is_object($record) && isset($record->{$valueAttribute}) => $record->{$valueAttribute},
            is_object($record) && isset($record->id) => $record->id,
            default => null,
        };
    }

    public function resolveRecordLabel(mixed $record): string
    {
        $displayAttribute = $this->displayAttribute;

        return match (true) {
            is_array($record) => (string) ($record[$displayAttribute] ?? $record['name'] ?? $record['id'] ?? ''),
            is_object($record) && isset($record->{$displayAttribute}) => (string) $record->{$displayAttribute},
            is_object($record) && isset($record->name) => (string) $record->name,
            is_object($record) && isset($record->id) => (string) $record->id,
            default => '',
        };
    }

    /**
     * @return array{
     *   key: string|int|null,
     *   label: string,
     *   columns: array<int, array<string, mixed>>,
     *   views: array<string, array{columns: array<int, array<string, mixed>>}>
     * }
     */
    public function serializeRecordForClientModal(mixed $record): array
    {
        $serialized = [
            'key' => $this->resolveRecordKey($record),
            'label' => $this->resolveRecordLabel($record),
            'columns' => $this->serializeColumnsForClientList($record, collect($this->resultColumns)
                ->filter(fn ($column) => $column instanceof DisplayField)
                ->values()
                ->all()),
            'views' => [],
        ];

        foreach ($this->getClientModalLists() as $list) {
            $serialized['views'][$list['key']] = [
                'columns' => $this->serializeColumnsForClientList($record, $list['columns']),
            ];
        }

        return $serialized;
    }

    public function resolveButtonFilterOptions(array|callable $definition): array
    {
        $options = $definition['options'] ?? [];

        if ($options instanceof Closure) {
            $options = $options();
        }

        return is_array($options) ? $options : [];
    }

    /**
     * @param  array<int, mixed>  $args
     */
    protected function invokeCallback(Closure $callback, array $args): mixed
    {
        $reflection = new \ReflectionFunction($callback);
        $parameterCount = $reflection->getNumberOfParameters();

        return $callback(...array_slice($args, 0, $parameterCount));
    }

    /**
     * @return array<int, stdClass>
     */
    protected function fallbackSearchResults(?string $query): array
    {
        $normalizedQuery = mb_strtolower(trim((string) ($query ?? '')));
        $records = [];

        foreach ($this->getOptions() as $value => $label) {
            $label = (string) $label;

            if ($normalizedQuery !== '' && ! str_contains(mb_strtolower($label), $normalizedQuery)) {
                continue;
            }

            $record = new stdClass;
            $record->{$this->valueAttribute} = $value;
            $record->{$this->displayAttribute} = $label;

            $records[] = $record;
        }

        return $records;
    }

    /**
     * @param  array<int|string>  $selectedIds
     * @return array<int, stdClass>
     */
    protected function fallbackSelectedRecords(array $selectedIds): array
    {
        $selectedLookup = array_map('strval', $selectedIds);
        $records = [];

        foreach ($this->getOptions() as $value => $label) {
            if (! in_array((string) $value, $selectedLookup, true)) {
                continue;
            }

            $record = new stdClass;
            $record->{$this->valueAttribute} = $value;
            $record->{$this->displayAttribute} = (string) $label;

            $records[] = $record;
        }

        return $records;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function defaultClientModalLists(): array
    {
        return [
            [
                'key' => 'results',
                'label' => 'Results',
                'rows' => 'resultRows',
                'loader' => 'default',
                'searchable' => true,
                'rowAction' => null,
                'emptyText' => 'No matches found.',
                'button' => [
                    'defaultLabel' => $this->selectButtonLabel,
                    'selectedLabel' => 'Selected',
                    'defaultColor' => 'zinc',
                    'selectedColor' => 'blue',
                    'action' => 'toggleRecord',
                ],
            ],
            [
                'key' => 'selected',
                'label' => 'Selected',
                'rows' => 'selectedRows',
                'sortable' => $this->sortable,
                'sortKey' => $this->sortable ? 'rowSortKey' : null,
                'loader' => null,
                'searchable' => false,
                'selectedState' => 'always',
                'rowAction' => null,
                'emptyText' => $this->emptySelectionText,
                'badge' => ['mode' => 'selected-count'],
                'button' => [
                    'defaultLabel' => 'Selected',
                    'selectedLabel' => 'Selected',
                    'defaultColor' => 'blue',
                    'selectedColor' => 'blue',
                    'action' => 'toggleRecord',
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $list
     * @return array<string, mixed>
     */
    protected function normalizeClientModalList(array $list, int $index): array
    {
        $key = (string) ($list['key'] ?? 'list_'.($index + 1));
        $rows = (string) ($list['rows'] ?? ($key === 'selected' ? 'selectedRows' : 'resultRows'));
        $sortable = (bool) ($list['sortable'] ?? ($rows === 'selectedRows' ? $this->sortable : false));
        $rowAction = array_key_exists('rowAction', $list) ? $list['rowAction'] : 'toggleRecord';
        $button = is_array($list['button'] ?? null) ? $list['button'] : [];
        $selectedState = $list['selectedState'] ?? ($rows === 'selectedRows' ? 'always' : 'isSelected');
        $columns = collect($list['columns'] ?? $this->resultColumns)
            ->filter(fn ($column) => $column instanceof DisplayField)
            ->values()
            ->all();
        $panelFields = collect($this->normalizeClientModalStateFields($list['panelFields'] ?? $this->clientModalStateFields))
            ->filter(function (array $field) use ($key): bool {
                $listKey = $field['listKey'] ?? null;

                return $listKey === null || $listKey === $key;
            })
            ->values()
            ->all();

        return [
            'key' => $key,
            'label' => (string) ($list['label'] ?? str($key)->replace(['_', '-'], ' ')->title()),
            'rows' => $rows,
            'loader' => $this->normalizeClientModalLoader($list['loader'] ?? ($rows === 'selectedRows' ? null : 'default')),
            'searchable' => (bool) ($list['searchable'] ?? ($rows !== 'selectedRows')),
            'searchPlaceholder' => isset($list['searchPlaceholder']) && is_string($list['searchPlaceholder']) && $list['searchPlaceholder'] !== ''
                ? $list['searchPlaceholder']
                : $this->searchPlaceholder,
            'rowAction' => $this->normalizeClientModalAction($rowAction),
            'sortable' => $sortable,
            'sortKey' => $sortable ? (string) ($list['sortKey'] ?? 'rowSortKey') : null,
            'selectedState' => is_bool($selectedState) ? ($selectedState ? 'always' : 'never') : (string) $selectedState,
            'emptyText' => (string) ($list['emptyText'] ?? ($rows === 'selectedRows' ? $this->emptySelectionText : 'No matches found.')),
            'button' => [
                'visible' => (bool) ($button['visible'] ?? true),
                'visibleField' => isset($button['visibleField']) && is_string($button['visibleField']) && $button['visibleField'] !== ''
                    ? $button['visibleField']
                    : null,
                'action' => $this->normalizeClientModalAction(array_key_exists('action', $button) ? $button['action'] : $rowAction),
                'icon' => isset($button['icon']) && is_string($button['icon']) && $button['icon'] !== ''
                    ? $button['icon']
                    : null,
                'iconOnly' => (bool) ($button['iconOnly'] ?? false),
                'defaultLabel' => (string) ($button['defaultLabel'] ?? ($rows === 'selectedRows' ? 'Selected' : $this->selectButtonLabel)),
                'selectedLabel' => (string) ($button['selectedLabel'] ?? 'Selected'),
                'defaultColor' => (string) ($button['defaultColor'] ?? ($rows === 'selectedRows' ? 'blue' : 'zinc')),
                'selectedColor' => (string) ($button['selectedColor'] ?? 'blue'),
            ],
            'saveButton' => [
                'visible' => (bool) data_get($list, 'saveButton.visible', false),
                'label' => (string) data_get($list, 'saveButton.label', 'Save'),
            ],
            'badge' => $this->normalizeClientModalBadge($list['badge'] ?? null, $rows),
            'itemFields' => $this->normalizeClientModalItemFields($list['itemFields'] ?? []),
            'panelFields' => $panelFields,
            'columns' => $columns,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function normalizeClientModalBadge(mixed $badge, string $rows): ?array
    {
        if ($badge === null && $rows === 'selectedRows') {
            return ['mode' => 'selected-count'];
        }

        if ($badge === null || $badge === false) {
            return null;
        }

        if ($badge === true) {
            return ['mode' => 'row-count'];
        }

        if (is_string($badge)) {
            return ['mode' => $badge];
        }

        return is_array($badge) ? $badge : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function normalizeClientModalLoader(mixed $loader): ?array
    {
        if ($loader === null || $loader === false) {
            return null;
        }

        if ($loader === 'default' || $loader === true) {
            return [
                'type' => 'default',
                'method' => null,
            ];
        }

        if (is_string($loader)) {
            return [
                'type' => 'wire',
                'method' => $loader,
            ];
        }

        if (! is_array($loader)) {
            return null;
        }

        return [
            'type' => $loader['type'] ?? 'wire',
            'method' => $loader['method'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeClientModalAction(mixed $action): array
    {
        if ($action === null || $action === false || $action === 'none') {
            return [];
        }

        if (is_string($action)) {
            return [
                'type' => 'local',
                'name' => $action,
                'passSelectedItems' => false,
                'passModalState' => false,
            ];
        }

        if (! is_array($action)) {
            return [
                'type' => 'local',
                'name' => 'toggleRecord',
                'passSelectedItems' => false,
                'passModalState' => false,
            ];
        }

        return [
            'type' => $action['type'] ?? 'local',
            'name' => $action['name'] ?? 'toggleRecord',
            'passSelectedItems' => (bool) ($action['passSelectedItems'] ?? true),
            'passModalState' => (bool) ($action['passModalState'] ?? false),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeClientModalItemFields(array $fields): array
    {
        return collect($fields)
            ->filter(fn (mixed $field) => is_array($field) && ($field['key'] ?? null))
            ->map(function (array $field): array {
                $options = collect($field['options'] ?? [])
                    ->map(function (mixed $label, mixed $value): array {
                        if (is_array($label)) {
                            return array_merge($label, [
                                'value' => (string) ($label['value'] ?? ''),
                                'label' => (string) ($label['label'] ?? $label['value'] ?? ''),
                            ]);
                        }

                        return [
                            'value' => (string) $value,
                            'label' => (string) $label,
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'key' => (string) $field['key'],
                    'label' => (string) ($field['label'] ?? str((string) $field['key'])->replace(['_', '-'], ' ')->title()),
                    'type' => (string) ($field['type'] ?? 'select'),
                    'placeholder' => (string) ($field['placeholder'] ?? '-'),
                    'clearable' => (bool) ($field['clearable'] ?? false),
                    'options' => $options,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeClientModalStateFields(array $fields): array
    {
        return collect($fields)
            ->map(function (mixed $field): ?array {
                if ($field instanceof Field) {
                    return $this->serializeClientModalStateField($field);
                }

                if (is_array($field) && isset($field['field']) && $field['field'] instanceof Field) {
                    return $this->serializeClientModalStateField($field['field'], $field);
                }

                if (! is_array($field) || ! (($field['key'] ?? null) || ($field['stateKey'] ?? null))) {
                    return null;
                }

                return $this->serializeClientModalStateField($field);
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>|Field  $field
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function serializeClientModalStateField(array|Field $field, array $overrides = []): array
    {
        if ($field instanceof Field) {
            $stateKey = (string) ($overrides['stateKey'] ?? $overrides['key'] ?? $field->name);
            $default = $overrides['default'] ?? $this->clientModalStateDefaults[$stateKey] ?? $field->default ?? null;
            $placeholder = method_exists($field, 'getPlaceholder') ? $field->getPlaceholder() : null;
            $variant = property_exists($field, 'variant') && is_string($field->variant) && $field->variant !== ''
                ? $field->variant
                : null;
            $optionView = property_exists($field, 'optionView') && is_string($field->optionView) && $field->optionView !== ''
                ? $field->optionView
                : null;
            $searchable = property_exists($field, 'searchable') ? (bool) $field->searchable : false;
            $clearable = property_exists($field, 'clearable') ? (bool) $field->clearable : false;
            $fieldMeta = $overrides['fieldMeta'] ?? [];

            if (property_exists($field, 'colorMap') && is_array($field->colorMap)) {
                $fieldMeta = [
                    ...$fieldMeta,
                    'colorMap' => $field->colorMap,
                ];
            }

            $options = method_exists($field, 'getOptions')
                ? collect($field->getOptions())
                    ->map(function (mixed $label, mixed $value): array {
                        if (is_array($label)) {
                            return [
                                'value' => (string) ($label['value'] ?? ''),
                                'label' => (string) ($label['label'] ?? $label['value'] ?? ''),
                            ];
                        }

                        return [
                            'value' => (string) $value,
                            'label' => (string) $label,
                        ];
                    })
                    ->values()
                    ->all()
                : [];

            return [
                'key' => $stateKey,
                'label' => (string) ($overrides['label'] ?? $field->getLabel()),
                'type' => (string) ($overrides['type'] ?? $field->type ?? 'text'),
                'placeholder' => (string) ($overrides['placeholder'] ?? $placeholder ?? ''),
                'required' => (bool) ($overrides['required'] ?? $field->required ?? false),
                'clearable' => (bool) ($overrides['clearable'] ?? $clearable),
                'searchable' => (bool) ($overrides['searchable'] ?? $searchable),
                'variant' => isset($overrides['variant']) && is_string($overrides['variant']) && $overrides['variant'] !== ''
                    ? $overrides['variant']
                    : $variant,
                'optionView' => isset($overrides['optionView']) && is_string($overrides['optionView']) && $overrides['optionView'] !== ''
                    ? $overrides['optionView']
                    : $optionView,
                'fieldMeta' => is_array($fieldMeta) ? $fieldMeta : [],
                'options' => isset($overrides['options']) && is_array($overrides['options'])
                    ? collect($overrides['options'])
                        ->map(function (mixed $label, mixed $value): array {
                            if (is_array($label)) {
                                return [
                                    'value' => (string) ($label['value'] ?? ''),
                                    'label' => (string) ($label['label'] ?? $label['value'] ?? ''),
                                ];
                            }

                            return [
                                'value' => (string) $value,
                                'label' => (string) $label,
                            ];
                        })
                        ->values()
                        ->all()
                    : $options,
                'default' => $default,
                'listKey' => isset($overrides['listKey']) && is_string($overrides['listKey']) && $overrides['listKey'] !== ''
                    ? $overrides['listKey']
                    : null,
            ];
        }

        $stateKey = (string) ($field['stateKey'] ?? $field['key']);
        $options = collect($field['options'] ?? [])
            ->map(function (mixed $label, mixed $value): array {
                if (is_array($label)) {
                    return [
                        'value' => (string) ($label['value'] ?? ''),
                        'label' => (string) ($label['label'] ?? $label['value'] ?? ''),
                    ];
                }

                return [
                    'value' => (string) $value,
                    'label' => (string) $label,
                ];
            })
            ->values()
            ->all();

        $default = $field['default'] ?? ($this->clientModalStateDefaults[$stateKey] ?? null);

        return [
            'key' => $stateKey,
            'label' => (string) ($field['label'] ?? str($stateKey)->replace(['_', '-'], ' ')->title()),
            'type' => (string) ($field['type'] ?? 'text'),
            'placeholder' => (string) ($field['placeholder'] ?? ''),
            'required' => (bool) ($field['required'] ?? false),
            'clearable' => (bool) ($field['clearable'] ?? false),
            'searchable' => (bool) ($field['searchable'] ?? false),
            'variant' => isset($field['variant']) && is_string($field['variant']) && $field['variant'] !== ''
                ? $field['variant']
                : null,
            'optionView' => isset($field['optionView']) && is_string($field['optionView']) && $field['optionView'] !== ''
                ? $field['optionView']
                : null,
            'fieldMeta' => is_array($field['fieldMeta'] ?? null) ? $field['fieldMeta'] : [],
            'options' => $options,
            'default' => $default,
            'listKey' => isset($field['listKey']) && is_string($field['listKey']) && $field['listKey'] !== ''
                ? $field['listKey']
                : null,
        ];
    }

    /**
     * @param  array<int, DisplayField>  $columns
     * @return array<int, array<string, mixed>>
     */
    protected function serializeColumnsForClientList(mixed $record, array $columns): array
    {
        return collect($columns)
            ->map(function (DisplayField $column) use ($record): array {
                $value = data_get($record, $column->field);

                if ($column instanceof CompactDisplayColumn) {
                    $compact = $column->getSourceData($record);
                    $title = $compact['title'] ?? null;
                    $badges = is_array($compact['badges'] ?? null) ? $compact['badges'] : [];
                    $meta = is_array($compact['meta'] ?? null) ? $compact['meta'] : [];
                    $allBadges = collect([...$badges, ...$meta])
                        ->map(fn (array $badge) => [
                            'label' => $badge['label'] ?? '',
                            'class' => isset($badge['color']) && is_string($badge['color']) && $badge['color'] !== ''
                                ? ColorPalette::lightBadge($badge['color'])
                                : '',
                        ])
                        ->values()
                        ->all();

                    return [
                        'type' => 'compact-display',
                        'title' => is_string($title) ? $title : '',
                        'badges' => $allBadges,
                    ];
                }

                if ($column instanceof BadgeColumn) {
                    $badges = $column->source ? $column->getSourceData($record) : collect((array) $value)
                        ->filter(fn ($badge) => $badge !== null && $badge !== '')
                        ->map(fn ($badge) => ['label' => (string) $badge])
                        ->values()
                        ->all();

                    return [
                        'type' => 'badges',
                        'badges' => collect($badges)
                            ->map(fn (array $badge) => [
                                'label' => $badge['label'] ?? '',
                                'class' => isset($badge['color']) && is_string($badge['color']) && $badge['color'] !== ''
                                    ? ColorPalette::lightBadge($badge['color'])
                                    : '',
                            ])
                            ->values()
                            ->all(),
                    ];
                }

                if ($column instanceof ColorBadgeColumn) {
                    return [
                        'type' => 'color-badge',
                        'label' => $value ? $column->formatValue($value) : '',
                        'class' => $value ? ColorPalette::lightBadge((string) $value) : '',
                    ];
                }

                if ($column instanceof TextColumn) {
                    return [
                        'type' => 'text',
                        'text' => (string) $column->formatValue($value),
                    ];
                }

                return [
                    'type' => 'text',
                    'text' => is_scalar($value) ? (string) $value : '',
                ];
            })
            ->values()
            ->all();
    }
}
