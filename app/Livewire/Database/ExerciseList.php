<?php

namespace App\Livewire\Database;

use App\Data\AbstractData;
use App\Data\Exercise\ExerciseData;
use App\Data\Exercise\ExerciseType;
use App\Form\TableColumn;
use App\Livewire\Concerns\AbstractModelList;
use App\Models\Exercise\Exercise;
use Illuminate\Contracts\Database\Eloquent\Builder;

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
}
