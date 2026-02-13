<?php

namespace App\Livewire\Training;

use App\Cms\Display\DisplayFields\Id;
use App\Cms\Display\DisplayFields\Text;
use App\Cms\Display\Table;
use App\Cms\Livewire\AbstractModelList;
use App\Data\Training\ProgramCategoryData;
use App\Models\ProgramCategory;
use Illuminate\Contracts\Database\Eloquent\Builder;

class ProgramCategoryList extends AbstractModelList
{
    protected function getDataClass(): string
    {
        return ProgramCategoryData::class;
    }

    protected function getBaseQuery(): Builder
    {
        return ProgramCategory::query();
    }

    protected function isSortable(): bool
    {
        return true;
    }

    protected function getTable(): Table
    {
        return Table::make()
            ->columns([
                Id::make(),
                Text::make('name')
                    ->label('Name')
                    ->width('w-1/2')
                    ->modal(),
                Text::make('color')
                    ->label('Color'),
            ]);
    }
}
