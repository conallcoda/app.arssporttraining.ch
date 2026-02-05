<?php

namespace App\Livewire\Database;

use App\Cms\Form\TableColumn;
use App\Livewire\Concerns\AbstractModelList;
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
            TableColumn::text('name')->label('Name')->modal(),
            TableColumn::relationship('members')->label('Members')->modal()->width('w-full'),
        ];
    }
}
