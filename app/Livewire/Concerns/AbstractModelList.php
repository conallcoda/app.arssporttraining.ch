<?php

namespace App\Livewire\Concerns;

use App\Data\AbstractData;
use App\Data\Form\FluxField;
use App\Data\Form\TableColumn;
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
    use WithPagination;

    public array $data = [];

    public ?int $editingId = null;

    public ?int $deletingId = null;

    public int $refreshKey = 0;

    abstract protected function getDataClass(): string;

    abstract protected function getBaseQuery(): Builder;

    protected function createDataFromForm(): AbstractData
    {
        return $this->getDataClass()::from($this->data);
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
        $this->resetForm();
        $this->emit();
    }

    #[Computed]
    public function fields(): array
    {
        $dataClass = $this->getDataClass();

        return $dataClass::getFields();
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
        return collect($this->fields)
            ->filter(fn (FluxField $field) => $field->type === 'relationship')
            ->map(fn (FluxField $field) => $field->name)
            ->values()
            ->all();
    }

    #[Computed(persist: false)]
    public function items()
    {
        $query = $this->getBaseQuery();

        if ($relationships = $this->getRelationshipsToLoad()) {
            $query->with($relationships);
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

    public function edit(int $id, ?string $focusField = null): void
    {
        $query = $this->getBaseQuery();

        if ($relationships = $this->getFormRelationshipsToLoad()) {
            $query->with($relationships);
        }

        $model = $query->findOrFail($id);
        $data = $this->dataFromModel($model);
        $this->editingId = $id;
        $this->data = $data->toArray();

        Flux::modal($this->getModalName())->show();

        if ($focusField) {
            $this->dispatch('focus-field', field: $focusField);
        }
    }

    public function save(): void
    {
        $this->validate(FluxField::buildValidationRules($this->fields, 'data.'));

        $data = $this->createDataFromForm();

        if ($this->editingId) {
            $data->id = $this->editingId;
        }

        $data->persist();

        $isNew = ! $this->editingId;
        $this->resetForm();

        if ($isNew) {
            $totalItems = $this->getBaseQuery()->count();
            $lastPage = (int) ceil($totalItems / $this->getPerPage());
            $this->setPage($lastPage);
        }

        Flux::modal($this->getModalName())->close();

        unset($this->items);
        $this->refreshKey++;
        $this->emit();
    }

    public function add(): void
    {
        $this->save();
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

    protected function resetForm(): void
    {
        $this->editingId = null;
        $dataClass = $this->getDataClass();
        $this->data = FluxField::buildDefaults($dataClass::getFields());
    }

    public function openAddModal(): void
    {
        $this->resetForm();
        Flux::modal($this->getModalName())->show();
    }

    public function addRelationshipItem(string $fieldName): void
    {
        if (! isset($this->data[$fieldName])) {
            $this->data[$fieldName] = [];
        }

        $field = collect($this->fields)->firstWhere('name', $fieldName);
        $newItem = [$field?->valueAttribute ?? 'id' => null];

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

        $field = collect($this->fields)->firstWhere('name', $fieldName);
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

        $field = collect($this->fields)->firstWhere('name', $fieldName);
        if ($field?->sortable) {
            foreach ($items as $i => $item) {
                $items[$i]['sort'] = $i;
            }
        }

        $this->data[$fieldName] = $items;
    }

    public function render(): View
    {
        return view('livewire.components.model-list', [
            'modalName' => $this->getModalName(),
            'deleteModalName' => $this->getDeleteModalName(),
            'entityName' => $this->getEntityName(),
        ]);
    }
}
