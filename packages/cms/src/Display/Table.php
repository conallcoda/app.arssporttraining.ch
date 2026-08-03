<?php

namespace Coda\Cms\Display;

use Closure;
use Coda\Cms\Display\DisplayFields\Id;
use Coda\Cms\Display\Pagination\Classic;
use Coda\FormKit\Field;

class Table
{
    protected array $columns = [];

    protected ?array $cardFields = null;

    protected ?CardDefinition $cardDefinition = null;

    protected string $cardLayout = 'grid';

    protected int $cardWidth = 260;

    protected string $cardMinWidth = '260px';

    protected ?string $cardItemClass = null;

    protected ?string $cardTitleField = null;

    protected ?string $cardView = null;

    protected ?string $masonryOverlayView = null;

    protected ?string $cardUrlField = null;

    protected ?Closure $cardUrlUsing = null;

    protected ?string $cardAlternateImageField = null;

    protected bool $cardLightbox = false;

    protected bool $showViewToggle = true;

    protected string $defaultView = 'table';

    protected ?Pagination $paginationConfig = null;

    protected array $actions = [];

    protected array $sortableFields = [];

    protected ?string $defaultSortField = null;

    protected string $defaultSortDirection = 'asc';

    /** @var TableFilter[] */
    protected array $filters = [];

    protected int $limit = 10;

    protected string $rowCellVerticalAlign = 'middle';

    public static function make(): static
    {
        return new static;
    }

    public function columns(array $columns): static
    {
        $this->columns = $columns;

        if ($this->defaultSortField === null) {
            foreach ($columns as $column) {
                if ($column instanceof Id) {
                    $this->defaultSortField = $column->field;
                    break;
                }
            }
        }

        return $this;
    }

    public function defaultSort(string $field, string $direction = 'asc'): static
    {
        $this->defaultSortField = $field;
        $this->defaultSortDirection = $direction;

        return $this;
    }

    public function actions(array $actions): static
    {
        $this->actions = $actions;

        return $this;
    }

    public function sortable(array $fields): static
    {
        $this->sortableFields = $fields;

        foreach ($this->columns as $column) {
            if (in_array($column->field, $fields, true) || in_array($column->sortField, $fields, true)) {
                $column->sortable();
            }
        }

        return $this;
    }

    /** @param TableFilter[] $filters */
    public function filters(array $filters): static
    {
        $this->filters = $filters;

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    public function rowCellVerticalAlign(string $align): static
    {
        $this->rowCellVerticalAlign = in_array($align, ['top', 'middle', 'bottom'], true) ? $align : 'middle';

        return $this;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function cards(array $fields): static
    {
        $this->cardFields = $fields;

        return $this;
    }

    public function getCards(): array
    {
        return $this->cardFields ?? [];
    }

    public function hasCards(): bool
    {
        return $this->cardDefinition !== null || $this->cardFields !== null;
    }

    public function cardDefinition(CardDefinition $definition): static
    {
        $this->cardDefinition = $definition;

        return $this;
    }

    public function getCardDefinition(): ?CardDefinition
    {
        if ($this->cardDefinition !== null) {
            if ($this->cardAlternateImageField !== null) {
                $this->cardDefinition->alternateImage($this->cardAlternateImageField);
            }

            return $this->cardDefinition;
        }

        if ($this->cardFields === null) {
            return null;
        }

        $definition = CardDefinition::fromDisplayFields(
            $this->cardFields,
            $this->cardTitleField,
            $this->cardView,
        );

        if ($this->cardAlternateImageField !== null) {
            $definition->alternateImage($this->cardAlternateImageField);
        }

        return $definition;
    }

    public function cardLayout(string $layout): static
    {
        $this->cardLayout = $layout;

        return $this;
    }

    public function getCardLayout(): string
    {
        return $this->cardLayout;
    }

    public function cardWidth(int $width): static
    {
        $this->cardWidth = $width;
        $this->cardMinWidth = $width.'px';

        return $this;
    }

    public function getCardWidth(): int
    {
        return $this->cardWidth;
    }

    public function cardMinWidth(int|string $width): static
    {
        $this->cardMinWidth = is_int($width) ? $width.'px' : $width;

        return $this;
    }

    public function getCardMinWidth(): string
    {
        return $this->cardMinWidth;
    }

    public function cardItemClass(string $class): static
    {
        $this->cardItemClass = $class;

        return $this;
    }

    public function getCardItemClass(): ?string
    {
        return $this->cardItemClass;
    }

    public function cardTitleField(string $field): static
    {
        $this->cardTitleField = $field;

        return $this;
    }

    public function getCardTitleField(): ?string
    {
        return $this->cardTitleField;
    }

    public function cardView(string $view): static
    {
        $this->cardView = $view;

        return $this;
    }

    public function getCardView(): ?string
    {
        return $this->cardView;
    }

    public function masonryOverlayView(string $view): static
    {
        $this->masonryOverlayView = $view;

        return $this;
    }

    public function getMasonryOverlayView(): ?string
    {
        return $this->masonryOverlayView;
    }

    public function cardUrlField(?string $field): static
    {
        $this->cardUrlField = $field;

        return $this;
    }

    public function getCardUrlField(): ?string
    {
        return $this->cardUrlField;
    }

    public function cardUrlUsing(?callable $resolver): static
    {
        $this->cardUrlUsing = $resolver instanceof Closure ? $resolver : ($resolver !== null ? $resolver(...) : null);

        return $this;
    }

    public function cardAlternateImageField(?string $field): static
    {
        $this->cardAlternateImageField = $field;

        return $this;
    }

    public function getCardAlternateImageField(): ?string
    {
        return $this->cardAlternateImageField;
    }

    public function cardLightbox(bool $enabled = true): static
    {
        $this->cardLightbox = $enabled;

        return $this;
    }

    public function resolveCardUrl(mixed $record, mixed $model = null): ?string
    {
        if ($this->cardUrlUsing instanceof Closure) {
            $url = ($this->cardUrlUsing)($record, $model);

            return is_string($url) && $url !== '' ? $url : null;
        }

        if ($this->cardUrlField !== null && $this->cardUrlField !== '') {
            $url = data_get($record, $this->cardUrlField);

            return is_string($url) && $url !== '' ? $url : null;
        }

        return null;
    }

    public function hasCardLightbox(): bool
    {
        return $this->cardLightbox;
    }

    public function showViewToggle(bool $show = true): static
    {
        $this->showViewToggle = $show;

        return $this;
    }

    public function shouldShowViewToggle(): bool
    {
        return $this->showViewToggle;
    }

    public function defaultView(string $view): static
    {
        $this->defaultView = $view;

        return $this;
    }

    public function getDefaultView(): string
    {
        return $this->defaultView;
    }

    public function pagination(Pagination $pagination): static
    {
        $this->paginationConfig = $pagination;

        return $this;
    }

    public function getPagination(): Pagination
    {
        return $this->paginationConfig ?? (new Classic)->perPage($this->limit);
    }

    public function getActions(): array
    {
        return $this->actions;
    }

    public function getSortableFields(): array
    {
        return $this->sortableFields;
    }

    public function hasActions(): bool
    {
        return count($this->actions) > 0;
    }

    /** @return TableFilter[] */
    public function getFilters(): array
    {
        return $this->filters;
    }

    public function hasFilters(): bool
    {
        return count($this->filters) > 0;
    }

    /** @return Field[] */
    public function getFilterFields(): array
    {
        return collect($this->filters)
            ->filter(fn (TableFilter $filter) => $filter->hasField())
            ->map(fn (TableFilter $filter) => $filter->getField())
            ->values()
            ->all();
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getRowCellVerticalAlign(): string
    {
        return $this->rowCellVerticalAlign;
    }

    public function getDefaultSortField(): ?string
    {
        return $this->defaultSortField;
    }

    public function getDefaultSortDirection(): string
    {
        return $this->defaultSortDirection;
    }

    public function hasDefaultSort(): bool
    {
        return $this->defaultSortField !== null;
    }

    public function getDefaultSortString(): string
    {
        if (! $this->defaultSortField) {
            return '';
        }

        return $this->defaultSortDirection === 'desc'
            ? "-{$this->defaultSortField}"
            : $this->defaultSortField;
    }
}
