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

    public ?string $modalTitle = null;

    public bool $deferModalApply = false;

    public bool $clientModal = false;

    public string $emptySelectionText = 'No items selected yet.';

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

    public function searchPlaceholder(string $placeholder): static
    {
        $this->searchPlaceholder = $placeholder;

        return $this;
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
     *   columns: array<int, array<string, mixed>>
     * }
     */
    public function serializeRecordForClientModal(mixed $record): array
    {
        $columns = collect($this->resultColumns)
            ->filter(fn ($column) => $column instanceof DisplayField)
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

        return [
            'key' => $this->resolveRecordKey($record),
            'label' => $this->resolveRecordLabel($record),
            'columns' => $columns,
        ];
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
}
