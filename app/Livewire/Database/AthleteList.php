<?php

namespace App\Livewire\Database;

use App\Cms\Display\DisplayFields\Ago;
use App\Cms\Display\DisplayFields\Id;
use App\Cms\Display\DisplayFields\Text;
use App\Cms\Display\Table;
use App\Cms\Livewire\AbstractModelList;
use App\Data\Athlete\AthleteData;
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

    protected function getTable(): Table
    {
        return Table::make()
            ->columns([
                Id::make(),
                Text::make('forename')
                    ->label('Forename')
                    ->width('w-1/3')
                    ->modal(),
                Text::make('surname')
                    ->label('Surname')
                    ->width('w-1/3')
                    ->modal(),
                Ago::make('updatedAt')->label('Last Changed'),
            ]);
    }
}
