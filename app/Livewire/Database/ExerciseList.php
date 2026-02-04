<?php

namespace App\Livewire\Database;

use App\Data\Form\Field;
use App\Data\Form\TableColumn;
use App\Livewire\Concerns\AbstractModelList;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseTypeEnum;
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
                ->enum(ExerciseTypeEnum::class)
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

        $type = $typeValue instanceof ExerciseTypeEnum
            ? $typeValue
            : ExerciseTypeEnum::tryFrom($typeValue);

        if (! $type) {
            return [];
        }

        $typeFieldsClass = ExerciseData::getTypeFieldsClass($type);

        if (! $typeFieldsClass) {
            return [];
        }

        return $typeFieldsClass::getFields();
    }

    public function updatedDataType(): void
    {
        $this->initializeTypeConfig();
    }

    protected function initializeTypeConfig(): void
    {
        $typeValue = $this->data['type'] ?? null;

        $type = $typeValue instanceof ExerciseTypeEnum
            ? $typeValue
            : ExerciseTypeEnum::tryFrom($typeValue);

        if ($type) {
            $typeFieldsClass = ExerciseData::getTypeFieldsClass($type);

            if ($typeFieldsClass) {
                $this->data['typeConfig'] = Field::buildDefaults($typeFieldsClass::getFields());
            } else {
                $this->data['typeConfig'] = [];
            }
        } else {
            $this->data['typeConfig'] = [];
        }
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
            $typeRules = Field::buildValidationRules($this->typeFields, 'data.typeConfig.');
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
