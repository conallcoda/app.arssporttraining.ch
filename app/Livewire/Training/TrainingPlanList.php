<?php

namespace App\Livewire\Training;

use App\Data\Form\TableColumn;
use App\Livewire\Concerns\AbstractModelList;
use App\Models\TrainingPlan;
use Illuminate\Contracts\Database\Eloquent\Builder;

class TrainingPlanList extends AbstractModelList
{
    protected function getDataClass(): string
    {
        return TrainingPlanData::class;
    }

    protected function getBaseQuery(): Builder
    {
        return TrainingPlan::query();
    }

    protected function getColumns(): array
    {
        return [
            TableColumn::id(),
            TableColumn::view('name', TrainingPlanView::class)->label('Name'),
            TableColumn::relationship('users')->label('Athletes'),
            TableColumn::relationship('userGroups')->label('Athlete Groups'),
        ];
    }
}
