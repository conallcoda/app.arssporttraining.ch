<?php

namespace App\Livewire\Database;

use App\Data\Athlete\AthleteGroupData;
use App\Models\Users\UserGroup;
use Coda\Cms\Display\DisplayFields\Ago;
use Coda\Cms\Display\DisplayFields\Relationship;
use Coda\Cms\Display\DisplayFields\Text;
use Coda\Cms\Display\Table;
use Coda\Cms\Livewire\AbstractModelList;
use Illuminate\Contracts\Database\Eloquent\Builder;

class AthleteGroupList extends AbstractModelList
{
    protected function getDataClass(): string
    {
        return AthleteGroupData::class;
    }

    protected function getBaseQuery(): Builder
    {
        return UserGroup::query();
    }

    protected function getTable(): Table
    {
        return Table::make()
            ->columns([
                Text::make('name')->label('Name')->modal(),
                Relationship::make('members')->label('Members')->modal()->width('w-full'),
                Ago::make('updatedAt')->label('Last Changed'),
            ]);
    }
}
