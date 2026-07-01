<?php

namespace Coda\Cms\Livewire;

use Coda\Cms\DetailsPageDefinition;
use Coda\Cms\Display\CardDefinition;
use Coda\Cms\Display\DisplayField;
use Coda\Cms\Display\DisplayFields\Relationship as RelationshipColumn;
use Coda\Cms\Display\IndexTab;
use Coda\Cms\Registry;
use Coda\Cms\Display\Table;
use Coda\Cms\Display\TableFilter;
use Coda\FormKit\Action;
use Flux\Flux;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

abstract class AbstractModelList extends Component
{
    use Concerns\InteractsWithCrudActions {
        Concerns\InteractsWithCrudActions::getListeners as getCrudActionListeners;
    }
    use Concerns\InteractsWithEntityDefinition;
    use Concerns\InteractsWithFormData;
    use Concerns\WithUrlPrefix;
    use WithPagination;

    public array $options = [];

    public array $routeParameters = [];

    public mixed $source = null;

    public array $data = [];

    public ?int $confirmingId = null;

    public ?string $confirmingAction = null;

    public int $refreshKey = 0;

    public bool $compact = false;

    public ?int $edit = null;

    public string $sort = '';

    public string $viewMode = '';

    public int $loadedPages = 1;

    public array $filters = [];

    public ?string $selectedTab = null;

    public string $pageName = '';

    protected ?Table $resolvedTable = null;

    abstract protected function getDataClass(): string;

    abstract protected function getBaseQuery(): Builder;

    abstract protected function getTable(): Table;

    public function setPageName(string $pageName): static
    {
        $this->pageName = $pageName;

        return $this;
    }

    protected function currentPageRouteName(): string
    {
        return $this->pageName !== ''
            ? $this->pageName
            : (string) request()->route()?->getName();
    }

    /** @return IndexTab[] */
    protected function getTabs(): array
    {
        return [];
    }

    #[Computed]
    public function tabs(): array
    {
        return $this->getTabs();
    }

    public function updatedSelectedTab(): void
    {
        $this->resetPage(pageName: $this->prefixedPageName());
        $this->resetState();
    }

    protected function getDefaultTabKey(): ?string
    {
        $tabs = $this->getTabs();

        return ! empty($tabs) ? $tabs[0]->key : null;
    }

    protected function getSelectedTab(): ?IndexTab
    {
        if ($this->selectedTab === null) {
            return null;
        }

        return collect($this->tabs)->first(fn (IndexTab $tab) => $tab->key === $this->selectedTab);
    }

    protected function isSortable(): bool
    {
        return false;
    }

    protected function usesManualSorting(): bool
    {
        return false;
    }

    protected function getSortColumn(): string
    {
        return 'sort';
    }

    protected function getSortActions(): array
    {
        if (! $this->isSortable() || $this->manualSortingEnabled()) {
            return [];
        }

        return [
            Action::make('moveUp', '')
                ->row()
                ->icon('chevron-up')
                ->disabledWhen('first'),
            Action::make('moveDown', '')
                ->row()
                ->icon('chevron-down')
                ->disabledWhen('last'),
        ];
    }

    protected function getExtraActions(): array
    {
        return $this->resolveTable()->getActions();
    }

    public function getListeners(): array
    {
        $listeners = $this->getCrudActionListeners();
        $listeners["{$this->editModalName}.delete-requested"] = 'handleFormDeleteRequested';

        return $listeners;
    }

    public function handleFormDeleteRequested(array $data): void
    {
        $id = $data['id'] ?? null;
        if ($id === null) {
            return;
        }

        Flux::modal($this->editModalName)->close();

        $this->confirmingId = (int) $id;
        $this->confirmingAction = 'delete';

        Flux::modal("confirm-form-delete-{$this->getEntitySlug()}")->show();
    }

    public function executeFormDelete(): void
    {
        if (! $this->confirmingId) {
            return;
        }

        $id = $this->confirmingId;
        $this->confirmingId = null;
        $this->confirmingAction = null;

        Flux::modal("confirm-form-delete-{$this->getEntitySlug()}")->close();

        $this->removeItem($id);
    }

    public function removeItem(int $id): void
    {
        $model = $this->getBaseQuery()->findOrFail($id);
        $name = $model->name ?? $this->getEntityName();
        $model->delete();

        Flux::toast(text: "{$name} deleted", variant: 'success');

        $this->resetState();
        $this->refreshKey++;
        $this->emit();
    }

    public function moveUp(int $id): void
    {
        if (! $this->isSortable() || $this->manualSortingEnabled()) {
            return;
        }

        $sortColumn = $this->getSortColumn();
        $item = $this->getBaseQuery()->findOrFail($id);
        $currentSort = $item->{$sortColumn};

        if ($currentSort <= 0) {
            return;
        }

        $previousItem = $this->getBaseQuery()
            ->where($sortColumn, $currentSort - 1)
            ->first();

        if ($previousItem) {
            $previousItem->{$sortColumn} = $currentSort;
            $previousItem->save();
        }

        $item->{$sortColumn} = $currentSort - 1;
        $item->save();

        $this->resetState();
        $this->refreshKey++;
        $this->emit();
    }

    public function moveDown(int $id): void
    {
        if (! $this->isSortable() || $this->manualSortingEnabled()) {
            return;
        }

        $sortColumn = $this->getSortColumn();
        $item = $this->getBaseQuery()->findOrFail($id);
        $currentSort = $item->{$sortColumn};
        $maxSort = $this->getBaseQuery()->max($sortColumn);

        if ($currentSort >= $maxSort) {
            return;
        }

        $nextItem = $this->getBaseQuery()
            ->where($sortColumn, $currentSort + 1)
            ->first();

        if ($nextItem) {
            $nextItem->{$sortColumn} = $currentSort;
            $nextItem->save();
        }

        $item->{$sortColumn} = $currentSort + 1;
        $item->save();

        $this->resetState();
        $this->refreshKey++;
        $this->emit();
    }

    public function sortBy(string $column): void
    {
        if ($this->manualSortingEnabled()) {
            return;
        }

        $table = $this->resolveTable();
        $sortableFields = $table->getSortableFields();

        if (! in_array($column, $sortableFields, true)) {
            return;
        }

        $effectiveSort = $this->effectiveSort();
        $defaultSort = $table->getDefaultSortString();

        if ($effectiveSort === $column) {
            $this->sort = "-{$column}";
        } elseif ($effectiveSort === "-{$column}") {
            if ($defaultSort === "-{$column}") {
                $this->sort = $column;
            } else {
                $this->sort = '';
            }
        } else {
            $this->sort = $column;
        }

        $this->resetPage(pageName: $this->prefixedPageName());
    }

    protected function effectiveSort(): string
    {
        if ($this->sort !== '') {
            return $this->sort;
        }

        return $this->resolveTable()->getDefaultSortString();
    }

    public function currentSortField(): string
    {
        return ltrim($this->effectiveSort(), '-');
    }

    public function currentSortDirection(): string
    {
        $effective = $this->effectiveSort();

        if ($effective !== '' && str_starts_with($effective, '-')) {
            return 'desc';
        }

        return 'asc';
    }

    public function isSortedBy(string $field): bool
    {
        $effective = $this->effectiveSort();

        return $effective !== '' && $this->currentSortField() === $field;
    }

    public function manualSortingEnabled(): bool
    {
        return $this->isSortable() && $this->usesManualSorting();
    }

    public function getActiveFilters(): array
    {
        return array_filter($this->filters, fn (mixed $value) => $value !== '' && $value !== null && $value !== []);
    }

    public function hasActiveFilters(): bool
    {
        return count($this->getActiveFilters()) > 0;
    }

    public function hasVisibleFilters(): bool
    {
        return count($this->filters) > 0;
    }

    public function filterFieldContextData(): array
    {
        return array_merge($this->getFormContextData(), [
            'filters' => $this->filters,
        ]);
    }

    public function activeFilterCount(): int
    {
        return count($this->getActiveFilters());
    }

    /** @return array<int, array{name: string, label: string, value: string}> */
    public function activeFilterBadges(): array
    {
        $activeFilters = $this->getActiveFilters();
        $tableFilters = collect($this->resolveTable()->getFilters())
            ->keyBy(fn (TableFilter $filter) => $filter->getName());

        $badges = [];

        foreach ($activeFilters as $name => $value) {
            $tableFilter = $tableFilters->get($name);

            if ($tableFilter) {
                $badges[] = [
                    'name' => $name,
                    'label' => $tableFilter->getLabel(),
                    'value' => $tableFilter->resolveDisplayValue($value),
                ];
            }
        }

        return $badges;
    }

    public function clearFilter(string $name): void
    {
        unset($this->filters[$name]);
        $this->resetPage(pageName: $this->prefixedPageName());
        $this->resetState();
    }

    public function applyFilters(): void
    {
        $this->filters = array_filter($this->filters, fn (mixed $value) => $value !== '' && $value !== null && $value !== []);
        $this->resetPage(pageName: $this->prefixedPageName());
        $this->resetState();
        Flux::modal($this->getEntitySlug().'-filters')->close();
    }

    public function clearFilters(): void
    {
        $this->filters = [];
        $this->resetPage(pageName: $this->prefixedPageName());
        $this->resetState();
    }

    public function updatedFilters(): void
    {
        $this->resetPage(pageName: $this->prefixedPageName());
    }

    protected function getEntitySlug(): string
    {
        return Str::of(class_basename($this))
            ->replaceLast('List', '')
            ->snake()
            ->slug()
            ->toString();
    }

    protected function getEntityName(): string
    {
        $dataClass = $this->getDataClass();

        if (method_exists($dataClass, 'getEntityName')) {
            $name = $dataClass::getEntityName();

            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        return Str::of(class_basename($this))
            ->replaceLast('List', '')
            ->headline()
            ->toString();
    }

    public function detailsPageDefinition(): ?DetailsPageDefinition
    {
        return app(Registry::class)
            ->pages()
            ->first(fn (mixed $page) => $page instanceof DetailsPageDefinition && $page->listComponent === static::class);
    }

    public function hasDetailsPage(): bool
    {
        return $this->detailsPageDefinition() !== null;
    }

    public function detailsPageUrl(Model $model): ?string
    {
        $page = $this->detailsPageDefinition();

        if ($page === null) {
            return null;
        }

        return app(Registry::class)->urlForRoute($page->name, ['record' => $model->getKey()]);
    }

    public function detailsTitleField(): ?string
    {
        foreach ($this->resolveTable()->getColumns() as $column) {
            if ($column->isTitleField()) {
                return $column->field;
            }
        }

        foreach ($this->resolveTable()->getCards() as $column) {
            if ($column->isTitleField()) {
                return $column->field;
            }
        }

        return null;
    }

    public function modelListDataClass(): string
    {
        return $this->getDataClass();
    }

    public function modelListEntityName(): string
    {
        return $this->getEntityName();
    }

    public function modelListDataFromModel(Model $model): array
    {
        return $this->dataFromModel($model)->toArray();
    }

    public function modelListResolveModel(int $id): ?Model
    {
        return $this->getBaseQuery()->find($id);
    }

    protected function resolveTable(): Table
    {
        return $this->resolvedTable ??= $this->getTable();
    }

    protected function resetState(): void
    {
        unset($this->items);
        $this->resolvedTable = null;
        $this->loadedPages = 1;
    }

    protected function getPerPage(): int
    {
        return $this->resolveTable()->getPagination()->getPerPage();
    }

    #[Computed]
    public function columns(): array
    {
        return $this->resolveTable()->getColumns();
    }

    #[Computed]
    public function cards(): array
    {
        return $this->resolveTable()->getCards();
    }

    #[Computed]
    public function cardsEnabled(): bool
    {
        return $this->resolveTable()->hasCards();
    }

    #[Computed]
    public function cardDefinition(): ?CardDefinition
    {
        return $this->resolveTable()->getCardDefinition();
    }

    #[Computed]
    public function cardLayout(): string
    {
        return $this->resolveTable()->getCardLayout();
    }

    #[Computed]
    public function cardWidth(): int
    {
        return $this->resolveTable()->getCardWidth();
    }

    #[Computed]
    public function cardMinWidth(): string
    {
        return $this->resolveTable()->getCardMinWidth();
    }

    #[Computed]
    public function cardItemClass(): ?string
    {
        return $this->resolveTable()->getCardItemClass();
    }

    #[Computed]
    public function cardTitleField(): ?string
    {
        return $this->resolveTable()->getCardTitleField();
    }

    #[Computed]
    public function cardView(): ?string
    {
        return $this->resolveTable()->getCardView();
    }

    #[Computed]
    public function masonryOverlayView(): ?string
    {
        return $this->resolveTable()->getMasonryOverlayView();
    }

    public function cardUrl(mixed $record, mixed $model = null): ?string
    {
        return $this->resolveTable()->resolveCardUrl($record, $model);
    }

    #[Computed]
    public function cardLightbox(): bool
    {
        return $this->resolveTable()->hasCardLightbox();
    }

    #[Computed]
    public function showViewToggle(): bool
    {
        return $this->resolveTable()->shouldShowViewToggle();
    }

    public function setView(string $mode): void
    {
        if (! in_array($mode, ['table', 'cards'], true)) {
            return;
        }

        if ($mode === 'cards' && ! $this->resolveTable()->hasCards()) {
            return;
        }

        $this->viewMode = $mode;
    }

    protected function urlProperties(): array
    {
        return [
            'edit' => ['except' => null],
            'sort' => ['except' => ''],
            'filters' => ['except' => []],
            'selectedTab' => ['except' => null],
            'viewMode' => ['except' => '', 'as' => 'view'],
        ];
    }

    protected function getDefaultOptions(): array
    {
        return [
            'showAddButton' => true,
            'showFilters' => true,
            'showPagination' => true,
            'compact' => false,
        ];
    }

    public function mount(...$routeParameters): void
    {
        $this->routeParameters = $routeParameters;

        if ($this->pageName === '') {
            $this->pageName = (string) request()->route()?->getName();
        }

        $this->mountEntityDefaults();
        $this->compact = $this->option('compact', $this->compact);

        if ($this->viewMode === '') {
            $this->viewMode = $this->resolveTable()->getDefaultView();
        }

        if ($this->viewMode === 'cards' && ! $this->resolveTable()->hasCards()) {
            $this->viewMode = 'table';
        }

        if ($this->selectedTab === null) {
            $this->selectedTab = $this->getDefaultTabKey();
        }

        if ($this->edit !== null) {
            $this->openEditFromUrl();
        }
    }

    public function routeParameter(string $key, mixed $default = null): mixed
    {
        if (property_exists($this, $key) && $this->{$key} !== null) {
            return $this->{$key};
        }

        return $this->routeParameters[$key] ?? $default;
    }

    protected function openEditFromUrl(): void
    {
        $model = $this->getBaseQuery()->find($this->edit);

        if (! $model) {
            $this->edit = null;

            return;
        }

        $page = $this->resolvePageForItem($this->edit);

        if ($page === null) {
            $this->edit = null;

            return;
        }

        $this->setPage($page, pageName: $this->prefixedPageName());

        $pageParam = $this->prefixedPageName();
        if ($page > 1 && (int) request()->query($pageParam, 1) !== $page) {
            $url = request()->fullUrlWithQuery([$pageParam => $page]);
            $this->js('history.replaceState({}, "", '.json_encode($url).')');
        }

        $data = $this->dataFromModel($model)->toArray();

        $this->dispatch("open-{$this->editModalName}", data: $data, title: 'Edit '.$this->getEntityName());
    }

    protected function resolvePageForItem(int $id): ?int
    {
        $allIds = $this->buildItemsQuery()->pluck('id');

        $position = $allIds->search($id);

        if ($position === false) {
            return null;
        }

        return (int) ceil(($position + 1) / $this->getPerPage());
    }

    protected function getRelationshipsToLoad(): array
    {
        return collect($this->resolveTable()->getColumns())
            ->filter(fn (DisplayField $column) => $column instanceof RelationshipColumn)
            ->map(fn (DisplayField $column) => $column->field)
            ->values()
            ->all();
    }

    protected function buildItemsQuery()
    {
        $query = $this->getBaseQuery();

        $selectedTab = $this->getSelectedTab();
        if ($selectedTab) {
            $selectedTab->applyQuery($query);
        }

        $table = $this->resolveTable();
        $activeFilters = $this->getActiveFilters();
        $needsQueryBuilder = $this->sort !== '' || ! empty($activeFilters) || ($table->hasDefaultSort() && ! $this->manualSortingEnabled());

        if ($needsQueryBuilder) {
            $modelClass = get_class($query->getModel());

            if (method_exists($modelClass, 'buildQueryBuilder')) {
                $requestParams = [];

                if ($this->sort !== '') {
                    $requestParams['sort'] = $this->sort;
                }

                if (! empty($activeFilters)) {
                    $requestParams['filter'] = $activeFilters;
                }

                $request = Request::create('/', 'GET', $requestParams);
                $queryBuilder = $modelClass::buildQueryBuilder($query, $request);

                if ($this->sort !== '') {
                    $queryBuilder->allowedSorts($queryBuilder->getDefinedSorts());
                }

                if (! $this->manualSortingEnabled()) {
                    $defaultSorts = $queryBuilder->getDefaultSorts();
                    $defaultSortString = $table->getDefaultSortString();

                    if (! empty($defaultSorts)) {
                        $queryBuilder->defaultSorts($defaultSorts);
                    } elseif ($defaultSortString !== '') {
                        $queryBuilder->defaultSort($defaultSortString);
                    }
                }

                if (! empty($activeFilters)) {
                    $allowedFilters = collect($table->getFilters())
                        ->map(fn (TableFilter $filter) => $filter->getAllowedFilter())
                        ->all();
                    $queryBuilder->allowedFilters($allowedFilters);
                }

                $query = $queryBuilder;
            }
        }

        $relationships = array_unique(array_merge(
            $this->getRelationshipsToLoad(),
            $this->getFormRelationshipsToLoad(),
        ));

        if ($relationships) {
            $query->with($relationships);
        }

        if ($this->isSortable()) {
            $query->orderBy($this->getSortColumn());
        }

        return $query;
    }

    #[Computed(persist: false)]
    public function items()
    {
        $pagination = $this->resolveTable()->getPagination();
        $query = $this->buildItemsQuery();

        if ($pagination->isAccumulating()) {
            return $query->paginate(
                $pagination->getPerPage() * max($this->loadedPages, 1),
                pageName: $this->prefixedPageName(),
                page: 1,
            );
        }

        return $query->paginate($pagination->getPerPage(), pageName: $this->prefixedPageName());
    }

    public function loadMore(): void
    {
        if (! $this->resolveTable()->getPagination()->isAccumulating()) {
            return;
        }

        $this->loadedPages++;
        unset($this->items);
    }

    public function reorderCurrentPage(array $orderedIds): void
    {
        if (! $this->manualSortingEnabled()) {
            Log::debug('cms.list.reorder.skipped', [
                'component' => static::class,
                'reason' => 'manual_sorting_disabled',
                'orderedIds' => $orderedIds,
            ]);
            return;
        }

        $normalizedIds = collect($orderedIds)
            ->filter(fn (mixed $id) => is_numeric($id))
            ->map(fn (mixed $id) => (int) $id)
            ->values();

        $pageItems = $this->items->getCollection()->values();
        $pageIds = $pageItems->pluck('id')->map(fn (mixed $id) => (int) $id)->values();

        if ($pageIds->sort()->values()->all() !== $normalizedIds->sort()->values()->all()) {
            Log::debug('cms.list.reorder.skipped', [
                'component' => static::class,
                'reason' => 'page_id_mismatch',
                'orderedIds' => $orderedIds,
                'normalizedIds' => $normalizedIds->all(),
                'pageIds' => $pageIds->all(),
            ]);
            return;
        }

        $sortColumn = $this->getSortColumn();
        $sortValues = $pageItems->pluck($sortColumn)->values()->all();
        $modelsById = $pageItems->keyBy('id');

        Log::debug('cms.list.reorder.start', [
            'component' => static::class,
            'orderedIds' => $normalizedIds->all(),
            'sortColumn' => $sortColumn,
            'sortValues' => $sortValues,
        ]);

        foreach ($normalizedIds as $index => $id) {
            $model = $modelsById->get($id);

            if (! $model) {
                continue;
            }

            $newSort = $sortValues[$index] ?? $index;

            if ($model->{$sortColumn} !== $newSort) {
                $model->{$sortColumn} = $newSort;
                $model->save();
            }
        }

        $this->resetState();
        $this->refreshKey++;
        $this->emit();
        Log::debug('cms.list.reorder.complete', [
            'component' => static::class,
            'orderedIds' => $normalizedIds->all(),
            'sortColumn' => $sortColumn,
        ]);
    }

    public function handleFormSubmitted(array $data): void
    {
        $persistedModelId = $data['id'] ?? null;

        if (empty($data['_persisted'])) {
            $model = $this->createDataFromForm($data);
            $model->persist();
            $persistedModelId = $model->id ?? $persistedModelId;
        }

        $isNew = ! empty($data['_persisted']) ? ! empty($data['_isNew']) : empty($data['id']);
        $name = $data['name'] ?? $this->getEntityName();
        $action = $isNew ? 'created' : 'updated';
        Flux::toast(text: "{$name} {$action}", variant: 'success');

        if ($isNew && $persistedModelId !== null) {
            $page = $this->resolvePageForItem((int) $persistedModelId);

            if ($page !== null) {
                $this->setPage($page, pageName: $this->prefixedPageName());
            }
        }

        $this->edit = null;
        $this->resetState();
        $this->refreshKey++;
        $this->emit();
    }

    protected function getFormModalMaxWidth(): string
    {
        $dataClass = $this->getDataClass();

        if (method_exists($dataClass, 'getFormModalMaxWidth')) {
            $width = $dataClass::getFormModalMaxWidth();

            if (is_string($width) && $width !== '') {
                return $width;
            }
        }

        return 'max-w-sm';
    }

    public function render(): View
    {
        $table = $this->resolveTable();

        return view('cms::model-list', [
            'entityName' => $this->getEntityName(),
            'entitySlug' => $this->getEntitySlug(),
            'compact' => $this->compact,
            'sortable' => $this->isSortable(),
            'sortableFields' => $table->getSortableFields(),
            'tableFilters' => $table->getFilters(),
            'filterFields' => $table->getFilterFields(),
            'options' => $this->options,
            'indexTabs' => $this->tabs,
            'pagination' => $table->getPagination(),
            'rowCellVerticalAlign' => $table->getRowCellVerticalAlign(),
        ]);
    }
}
