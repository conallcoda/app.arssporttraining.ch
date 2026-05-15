<?php

namespace App\Form\Fields;

use Coda\Cms\Display\DisplayField;
use Coda\Cms\Display\DisplayFields\Badge as BadgeColumn;
use Coda\Cms\Display\DisplayFields\ColorBadge as ColorBadgeColumn;
use Coda\Cms\Display\DisplayFields\CompactDisplay as CompactDisplayColumn;
use Coda\Cms\Display\DisplayFields\Text as TextColumn;
use Coda\Cms\Support\ColorPalette;

class RelationshipSelector extends \Coda\FormKit\Fields\RelationshipSelector
{
    public bool $clientModal = true;

    public bool $deferModalApply = true;

    public string $inlineSelectionDisplay = 'default';

    public bool $showInlineSchema = true;

    public ?string $triggerButtonLabel = null;

    public string $triggerButtonIcon = 'plus';

    /** @var array<int, array<string, mixed>> */
    public array $clientModalLists = [];

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
                'rowAction' => $list['rowAction'],
                'sortable' => $list['sortable'],
                'sortKey' => $list['sortKey'],
                'selectedState' => $list['selectedState'],
                'emptyText' => $list['emptyText'],
                'button' => $list['button'],
                'badge' => $list['badge'],
                'itemFields' => $list['itemFields'],
            ])
            ->values()
            ->all();
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
        $serialized = parent::serializeRecordForClientModal($record);
        $serialized['views'] = [];

        foreach (['selector_program_has_exercises', 'selector_program_exercise_count'] as $attribute) {
            $value = data_get($record, $attribute);

            if ($value !== null) {
                $serialized[$attribute] = $value;
            }
        }

        foreach ($this->getClientModalLists() as $list) {
            $serialized['views'][$list['key']] = [
                'columns' => $this->serializeColumnsForClientList($record, $list['columns']),
            ];
        }

        return $serialized;
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
                'selectedState' => 'isSelected',
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

        return [
            'key' => $key,
            'label' => (string) ($list['label'] ?? str($key)->replace(['_', '-'], ' ')->title()),
            'rows' => $rows,
            'loader' => $this->normalizeClientModalLoader($list['loader'] ?? ($rows === 'selectedRows' ? null : 'default')),
            'searchable' => (bool) ($list['searchable'] ?? ($rows !== 'selectedRows')),
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
            'badge' => $this->normalizeClientModalBadge($list['badge'] ?? null, $rows),
            'itemFields' => $this->normalizeClientModalItemFields($list['itemFields'] ?? []),
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
            ];
        }

        if (! is_array($action)) {
            return [
                'type' => 'local',
                'name' => 'toggleRecord',
            ];
        }

        return [
            'type' => $action['type'] ?? 'local',
            'name' => $action['name'] ?? 'toggleRecord',
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
