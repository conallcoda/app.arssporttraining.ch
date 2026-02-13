<?php

namespace App\Cms\Livewire;

use App\Cms\Data\AbstractData;
use App\Cms\Display\DisplayField;
use App\Cms\Display\DisplayFields\Relationship as RelationshipColumn;
use App\Cms\Display\Table;
use App\Cms\Display\TableFilter;
use App\Cms\Form\Action;
use App\Cms\Form\ActionPlacement;
use App\Cms\Form\Field;
use App\Cms\Form\Fields\Relationship;
use App\Cms\Form\Form;
use Flux\Flux;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

abstract class AbstractModelList extends Component
{
    use Concerns\InteractsWithFormData;
    use WithPagination;

    public array $data = [];

    public ?int $confirmingId = null;

    public ?string $confirmingAction = null;

    public int $refreshKey = 0;

    public bool $compact = false;

    #[Url]
    public string $sort = '';

    #[Url]
    public array $filters = [];

    abstract protected function getDataClass(): string;

    abstract protected function getBaseQuery(): Builder;

    abstract protected function getTable(): Table;

    protected function isSortable(): bool
    {
        return false;
    }

    protected function getSortColumn(): string
    {
        return 'sort';
    }

    protected function getActions(): array
    {
        return array_filter([
            $this->getAddAction(),
            $this->getEditAction(),
            $this->getDeleteAction(),
            ...$this->getSortActions(),
            ...$this->getExtraActions(),
        ]);
    }

    protected function getAddAction(): Action
    {
        return Action::make('add', 'Add '.$this->getEntityName())
            ->header()
            ->icon('plus')
            ->variant('primary')
            ->formModal($this->getDataClass(), 'Add '.$this->getEntityName())
            ->handler('handleFormSubmitted');
    }

    protected function getEditAction(): Action
    {
        return Action::make('edit', 'Edit')
            ->row()
            ->icon('pencil')
            ->formModal($this->getDataClass(), 'Edit '.$this->getEntityName())
            ->passesItemData()
            ->handler('handleFormSubmitted');
    }

    protected function getDeleteAction(): Action
    {
        return Action::make('delete', 'Delete')
            ->row()
            ->icon('trash-2')
            ->confirm(
                heading: 'Delete '.$this->getEntityName().'?',
                description: "You're about to delete this ".strtolower($this->getEntityName()).".\nThis action cannot be reversed.",
                buttonLabel: 'Delete',
            )
            ->handler('removeItem');
    }

    protected function getSortActions(): array
    {
        if (! $this->isSortable()) {
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

    #[Computed]
    public function actions(): array
    {
        return $this->getActions();
    }

    #[Computed]
    public function headerActions(): array
    {
        return collect($this->actions)
            ->filter(fn (Action $a) => $a->placement === ActionPlacement::Header)
            ->values()
            ->all();
    }

    #[Computed]
    public function rowActions(): array
    {
        return collect($this->actions)
            ->filter(fn (Action $a) => $a->placement === ActionPlacement::Row)
            ->values()
            ->all();
    }

    #[Computed]
    public function rowMenuActions(): array
    {
        return collect($this->actions)
            ->filter(fn (Action $a) => $a->placement === ActionPlacement::RowMenu)
            ->values()
            ->all();
    }

    #[Computed]
    public function formModals(): array
    {
        $modals = [];
        $seenFormClasses = [];

        foreach ($this->actions as $action) {
            if (! $action->isFormModal()) {
                continue;
            }

            if (in_array($action->formDataClass, $seenFormClasses, true)) {
                continue;
            }

            $seenFormClasses[] = $action->formDataClass;
            $modals[] = [
                'name' => $action->resolveModalName($this->getEntitySlug()),
                'title' => $action->modalTitle,
                'formDataClass' => $action->formDataClass,
                'submitLabel' => $action->submitLabel ?? 'Save',
                'formComponent' => $action->formComponent,
            ];
        }

        return $modals;
    }

    #[Computed]
    public function confirmModals(): array
    {
        return collect($this->actions)
            ->filter(fn (Action $a) => $a->isConfirm())
            ->values()
            ->all();
    }

    #[Computed]
    public function editModalName(): string
    {
        foreach ($this->actions as $action) {
            if ($action->isFormModal() && $action->formDataClass === $this->getDataClass()) {
                return $action->resolveModalName($this->getEntitySlug());
            }
        }

        return 'add-'.$this->getEntitySlug();
    }

    public function getModalNameForAction(Action $action): string
    {
        if (! $action->isFormModal()) {
            return $action->resolveModalName($this->getEntitySlug());
        }

        foreach ($this->actions as $other) {
            if ($other->isFormModal() && $other->formDataClass === $action->formDataClass) {
                return $other->resolveModalName($this->getEntitySlug());
            }
        }

        return $action->resolveModalName($this->getEntitySlug());
    }

    /** @return array<string, string> */
    public function getListeners(): array
    {
        $listeners = [];

        foreach ($this->getActions() as $action) {
            if (! $action->isFormModal()) {
                continue;
            }

            $modalName = $action->resolveModalName($this->getEntitySlug());
            $key = "{$modalName}.submitted";

            if (! isset($listeners[$key])) {
                $listeners[$key] = $action->getHandler();
            }
        }

        return $listeners;
    }

    public function confirmAction(string $actionName, int $id): void
    {
        $action = collect($this->actions)->firstWhere('name', $actionName);
        if (! $action || ! $action->isConfirm()) {
            return;
        }

        $this->confirmingId = $id;
        $this->confirmingAction = $actionName;
        Flux::modal($action->resolveModalName($this->getEntitySlug()))->show();
    }

    public function executeConfirmedAction(): void
    {
        if (! $this->confirmingId || ! $this->confirmingAction) {
            return;
        }

        $action = collect($this->actions)->firstWhere('name', $this->confirmingAction);
        if (! $action) {
            return;
        }

        $handler = $action->getHandler();
        $id = $this->confirmingId;

        $this->confirmingId = null;
        $this->confirmingAction = null;

        Flux::modal($action->resolveModalName($this->getEntitySlug()))->close();

        $this->$handler($id);
    }

    public function openActionModal(string $actionName, int $id): void
    {
        $action = collect($this->actions)->firstWhere('name', $actionName);
        if (! $action || ! $action->isFormModal()) {
            return;
        }

        $modalName = $this->getModalNameForAction($action);
        $data = ['id' => $id];

        if ($action->prepareData) {
            $model = $this->getBaseQuery()->findOrFail($id);
            $data = ($action->prepareData)($model, $data);
        }

        $this->dispatch("open-{$modalName}", data: $data, title: $action->modalTitle);
    }

    public function removeItem(int $id): void
    {
        $this->getBaseQuery()->where('id', $id)->delete();

        unset($this->items);
        $this->refreshKey++;
        $this->emit();
    }

    public function moveUp(int $id): void
    {
        if (! $this->isSortable()) {
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

        unset($this->items);
        $this->refreshKey++;
        $this->emit();
    }

    public function moveDown(int $id): void
    {
        if (! $this->isSortable()) {
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

        unset($this->items);
        $this->refreshKey++;
        $this->emit();
    }

    public function sortBy(string $column): void
    {
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

        $this->resetPage();
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

    public function getActiveFilters(): array
    {
        return array_filter($this->filters, fn (mixed $value) => $value !== '' && $value !== null);
    }

    public function hasActiveFilters(): bool
    {
        return count($this->getActiveFilters()) > 0;
    }

    public function hasVisibleFilters(): bool
    {
        return count($this->filters) > 0;
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
        $this->resetPage();
        unset($this->items);
    }

    public function applyFilters(): void
    {
        $this->filters = array_filter($this->filters, fn (mixed $value) => $value !== '' && $value !== null);
        $this->resetPage();
        unset($this->items);
        Flux::modal($this->getEntitySlug().'-filters')->close();
    }

    public function clearFilters(): void
    {
        $this->filters = [];
        $this->resetPage();
        unset($this->items);
    }

    public function updatedFilters(): void
    {
        $this->resetPage();
    }

    protected function createDataFromForm(array $formData): AbstractData
    {
        return $this->getDataClass()::from($formData);
    }

    protected function getEntitySlug(): string
    {
        return Str::of(class_basename($this))
            ->replaceLast('List', '')
            ->snake()
            ->slug()
            ->toString();
    }

    protected function getEventName(): string
    {
        return Str::plural($this->getEntitySlug()).'-updated';
    }

    protected function getEventDataKey(): string
    {
        return Str::plural($this->getEntitySlug());
    }

    protected function getEntityName(): string
    {
        return Str::of(class_basename($this))
            ->replaceLast('List', '')
            ->headline()
            ->toString();
    }

    protected function dataFromModel(Model $model): AbstractData
    {
        return $this->getDataClass()::from($model);
    }

    protected function resolveTable(): Table
    {
        return $this->getTable();
    }

    protected function getPerPage(): int
    {
        return $this->resolveTable()->getLimit();
    }

    #[Computed]
    public function columns(): array
    {
        return $this->resolveTable()->getColumns();
    }

    public function mount(): void
    {
        $this->data = $this->buildDefaultsFromFieldsets();
        $this->emit();
    }

    protected function getFormDefinition(): Form|array
    {
        $dataClass = $this->getDataClass();

        if (method_exists($dataClass, 'getForm')) {
            return $dataClass::getForm();
        }

        if (method_exists($dataClass, 'getFields')) {
            return $dataClass::getFields();
        }

        return [];
    }

    #[Computed]
    public function formConfig(): Form
    {
        $definition = $this->getFormDefinition();

        if ($definition instanceof Form) {
            return $definition;
        }

        return Form::fields($definition);
    }

    #[Computed]
    public function fields(): array
    {
        return $this->formConfig->getFields();
    }

    #[Computed]
    public function fieldsets(): array
    {
        return $this->formConfig->resolveFieldsets($this->data);
    }

    protected function getRelationshipsToLoad(): array
    {
        return collect($this->resolveTable()->getColumns())
            ->filter(fn (DisplayField $column) => $column instanceof RelationshipColumn)
            ->map(fn (DisplayField $column) => $column->field)
            ->values()
            ->all();
    }

    protected function getFormRelationshipsToLoad(): array
    {
        return collect($this->getAllFields())
            ->filter(fn (Field $field) => $field instanceof Relationship)
            ->map(fn (Field $field) => $field->name)
            ->values()
            ->all();
    }

    #[Computed(persist: false)]
    public function items()
    {
        $query = $this->getBaseQuery();
        $table = $this->resolveTable();
        $activeFilters = $this->getActiveFilters();
        $needsQueryBuilder = $this->sort !== '' || ! empty($activeFilters) || $table->hasDefaultSort();

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

                $defaultSortString = $table->getDefaultSortString();
                if ($defaultSortString !== '') {
                    $queryBuilder->defaultSort($defaultSortString);
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

        return $query->paginate($this->getPerPage());
    }

    public function handleFormSubmitted(array $data): void
    {
        $model = $this->createDataFromForm($data);
        $model->persist();

        $isNew = empty($data['id']);

        if ($isNew) {
            $totalItems = $this->getBaseQuery()->count();
            $lastPage = (int) ceil($totalItems / $this->getPerPage());
            $this->setPage($lastPage);
        }

        unset($this->items);
        $this->refreshKey++;
        $this->emit();
    }

    protected function emit(): void
    {
        $query = $this->getBaseQuery();

        if ($relationships = $this->getFormRelationshipsToLoad()) {
            $query->with($relationships);
        }

        $allItems = $query
            ->get()
            ->map(fn (Model $model) => $this->dataFromModel($model)->toArray())
            ->all();

        $this->dispatch($this->getEventName(), ...[$this->getEventDataKey() => $allItems]);
    }

    public function render(): View
    {
        $table = $this->resolveTable();

        return view('livewire.components.model-list', [
            'entityName' => $this->getEntityName(),
            'entitySlug' => $this->getEntitySlug(),
            'compact' => $this->compact,
            'sortable' => $this->isSortable(),
            'sortableFields' => $table->getSortableFields(),
            'tableFilters' => $table->getFilters(),
            'filterFields' => $table->getFilterFields(),
        ]);
    }
}
