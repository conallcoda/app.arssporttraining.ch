<?php

namespace App\Livewire\Database;

use App\Data\AbstractData;
use App\Data\Exercise\ExerciseData;
use App\Data\Exercise\ExerciseType;
use App\Form\Field;
use App\Form\TableColumn;
use App\Livewire\Concerns\AbstractModelList;
use App\Models\Exercise\Exercise;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Computed;

class ExerciseList extends AbstractModelList
{
    protected function getDataClass(): string
    {
        return ExerciseData::class;
    }

    protected function getBaseQuery(): Builder
    {
        return Exercise::query();
    }

    protected function createDataFromForm(): AbstractData
    {
        return ExerciseData::from($this->data);
    }

    protected function getColumns(): array
    {
        return [
            TableColumn::id(),
            TableColumn::text('name')
                ->label('Name')
                ->width('w-1/3')
                ->modal(),
            TableColumn::text('type')
                ->label('Type')
                ->width('w-1/6')
                ->enum(ExerciseType::class)
                ->modal()
                ->badge(),
            TableColumn::text('defaults')
                ->label('Defaults')
                ->badge()
                ->source(fn (ExerciseData $data) => $data->getDefaultsBadges()),
        ];
    }

    #[Computed]
    public function typeFields(): array
    {
        $typeValue = $this->data['type'] ?? null;

        if (! $typeValue) {
            return [];
        }

        $type = $typeValue instanceof ExerciseType
            ? $typeValue
            : ExerciseType::tryFrom($typeValue);

        if (! $type) {
            return [];
        }

        return $type->getFields();
    }

    public function updatedDataType(): void
    {
        $this->initializeTypeConfig();
    }

    protected function initializeTypeConfig(): void
    {
        $typeValue = $this->data['type'] ?? null;

        $type = $typeValue instanceof ExerciseType
            ? $typeValue
            : ExerciseType::tryFrom($typeValue);

        $this->data['config'] = $type
            ? Field::buildDefaults($type->getFields())
            : [];
    }

    protected function resetForm(): void
    {
        parent::resetForm();
        $this->initializeTypeConfig();
    }

    protected function getValidationRules(): array
    {
        $rules = Field::buildValidationRules($this->fields, 'data.');

        if (count($this->typeFields) > 0) {
            $typeRules = Field::buildValidationRules($this->typeFields, 'data.config.');
            $rules = array_merge($rules, $typeRules);
        }

        return $rules;
    }

    public function save(): void
    {
        $this->validate($this->getValidationRules());

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

        \Flux\Flux::modal($this->getModalName())->close();

        unset($this->items);
        $this->refreshKey++;
        $this->emit();
    }

    public function render(): View
    {
        return view('livewire.database.exercise-list', [
            'modalName' => $this->getModalName(),
            'deleteModalName' => $this->getDeleteModalName(),
            'entityName' => $this->getEntityName(),
        ]);
    }
}
