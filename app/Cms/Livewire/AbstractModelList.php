<?php

namespace App\Cms\Livewire;

use App\Cms\Data\AbstractData;
use App\Cms\Form\Field;
use App\Cms\Form\Fields\Relationship;
use App\Cms\Form\Form;
use App\Cms\Form\TableColumn;
use Flux\Flux;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

abstract class AbstractModelList extends Component
{
    use Concerns\InteractsWithFormData;
    use WithPagination;

    public array $data = [];

    protected function getRowActions(): array
    {
        return [];
    }

    #[Computed]
    public function rowActions(): array
    {
        return $this->getRowActions();
    }

    public ?int $deletingId = null;

    public int $refreshKey = 0;

    public bool $compact = false;

    abstract protected function getDataClass(): string;

    protected function isSortable(): bool
    {
        return false;
    }

    protected function getSortColumn(): string
    {
        return 'sort';
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

    abstract protected function getBaseQuery(): Builder;

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

    protected function getModalName(): string
    {
        return 'add-'.$this->getEntitySlug();
    }

    protected function getEntityName(): string
    {
        return Str::of(class_basename($this))
            ->replaceLast('List', '')
            ->headline()
            ->toString();
    }

    abstract protected function getColumns(): array;

    protected function dataFromModel(Model $model): AbstractData
    {
        return $this->getDataClass()::from($model);
    }

    protected function getPerPage(): int
    {
        return 10;
    }

    #[Computed]
    public function columns(): array
    {
        return $this->getColumns();
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
        return collect($this->getColumns())
            ->filter(fn (TableColumn $column) => $column->type === 'relationship')
            ->map(fn (TableColumn $column) => $column->field)
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

    public function update(int $id, string $field, mixed $value): void
    {
        $model = $this->getBaseQuery()->findOrFail($id);
        $data = $this->dataFromModel($model);
        $data->{$field} = $value;
        $data->persist();

        unset($this->items);
        $this->emit();
    }

    /** @return array<string, string> */
    public function getListeners(): array
    {
        return [
            "{$this->getModalName()}.submitted" => 'handleFormSubmitted',
            "{$this->getDuplicateModalName()}.submitted" => 'handleDuplicateSubmitted',
        ];
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

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
        Flux::modal($this->getDeleteModalName())->show();
    }

    public function remove(): void
    {
        if (! $this->deletingId) {
            return;
        }

        $this->getBaseQuery()->where('id', $this->deletingId)->delete();

        $this->deletingId = null;
        unset($this->items);
        $this->refreshKey++;
        $this->emit();

        Flux::modal($this->getDeleteModalName())->close();
    }

    protected function getDeleteModalName(): string
    {
        return 'delete-'.$this->getEntitySlug();
    }

    public function confirmDuplicate(int $id): void
    {
        $model = $this->getBaseQuery()->findOrFail($id);
        $defaultName = $this->getDuplicateDefaultName($model);

        $this->dispatch("open-{$this->getDuplicateModalName()}", data: ['id' => $id, 'name' => $defaultName]);
    }

    protected function getDuplicateDefaultName(Model $model): string
    {
        return ($model->name ?? '').' (Copy)';
    }

    public function handleDuplicateSubmitted(array $data): void
    {
        $this->performDuplicate($data);

        unset($this->items);
        $this->refreshKey++;
        $this->emit();
    }

    protected function performDuplicate(array $data): void {}

    protected function getDuplicateModalName(): string
    {
        return 'duplicate-'.$this->getEntitySlug();
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
        return view('livewire.components.model-list', [
            'modalName' => $this->getModalName(),
            'deleteModalName' => $this->getDeleteModalName(),
            'duplicateModalName' => $this->getDuplicateModalName(),
            'entityName' => $this->getEntityName(),
            'entitySlug' => $this->getEntitySlug(),
            'dataClass' => $this->getDataClass(),
            'compact' => $this->compact,
            'sortable' => $this->isSortable(),
            'sortColumn' => $this->getSortColumn(),
            'rowActions' => $this->rowActions,
        ]);
    }
}
