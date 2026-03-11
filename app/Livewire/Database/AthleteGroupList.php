<?php

namespace App\Livewire\Database;

use App\Data\Athlete\AthleteGroupData;
use App\Models\Users\UserGroup;
use Coda\Cms\Display\DisplayFields\Ago;
use Coda\Cms\Display\DisplayFields\Relationship;
use Coda\Cms\Display\DisplayFields\Text;
use Coda\Cms\Display\Table;
use Coda\Cms\Display\TableFilter;
use Coda\Cms\Form\Fields\Text as TextField;
use Coda\Cms\Livewire\AbstractModelList;
use Illuminate\Contracts\Database\Eloquent\Builder;

class AthleteGroupList extends AbstractModelList
{
    protected function urlPrefix(): string
    {
        return 'agl_';
    }

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
                Text::make('name')->label(__('Name'))->modal(),
                Relationship::make('members')->label(__('Members'))->modal()->width('w-full'),
                Ago::make('updatedAt')->label(__('Last Changed')),
            ])
            ->filters([
                TableFilter::callback('search', function (Builder $query, mixed $value): void {
                    $query->where(function (Builder $query) use ($value): void {
                        $query->where('name', 'like', '%'.$value.'%')
                            ->orWhereHas('members', function (Builder $query) use ($value): void {
                                $query->where('forename', 'like', '%'.$value.'%')
                                    ->orWhere('surname', 'like', '%'.$value.'%');
                            });
                    });
                })
                    ->field(
                        TextField::make('search')
                            ->label(__('Search'))
                            ->placeholder(__('Search groups...'))
                    ),
            ]);
    }
}
