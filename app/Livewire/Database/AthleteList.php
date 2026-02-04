<?php

namespace App\Livewire\Database;

use App\Form\TableColumn;
use App\Livewire\Concerns\AbstractModelList;
use App\Models\Users\User;
use App\Models\Users\UserTypeEnum;
use Illuminate\Contracts\Database\Eloquent\Builder;

class AthleteList extends AbstractModelList
{
    protected function getDataClass(): string
    {
        return AthleteData::class;
    }

    protected function getBaseQuery(): Builder
    {
        return User::query()->where('type', UserTypeEnum::Athlete);
    }

    protected function getColumns(): array
    {
        return [
            TableColumn::id(),
            TableColumn::text('forename')
                ->label('Forename')
                ->width('w-1/3')
                ->modal(),
            TableColumn::text('surname')
                ->label('Surname')
                ->width('w-1/3')
                ->modal(),
        ];
    }
}
