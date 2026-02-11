<?php

namespace App\Livewire\Database;

use App\Cms\Display\DisplayFields\Relationship;
use App\Cms\Display\DisplayFields\Text;
use App\Cms\Livewire\AbstractModelList;
use App\Data\Athlete\AthleteGroupData;
use App\Models\Users\UserGroup;
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

    protected function getColumns(): array
    {
        return [
            Text::make('name')->label('Name')->modal(),
            Relationship::make('members')->label('Members')->modal()->width('w-full'),
        ];
    }
}
