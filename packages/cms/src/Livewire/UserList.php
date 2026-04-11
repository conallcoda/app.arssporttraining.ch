<?php

namespace Coda\Cms\Livewire;

use Coda\Cms\Data\UserData;
use Coda\Cms\Display\DisplayFields\Ago;
use Coda\Cms\Display\DisplayFields\Id;
use Coda\Cms\Display\DisplayFields\PersonName;
use Coda\Cms\Display\DisplayFields\Text;
use Coda\Cms\Display\Table;
use Coda\Cms\Display\TableFilter;
use Coda\FormKit\Fields\Text as TextField;
use Illuminate\Contracts\Database\Eloquent\Builder;

class UserList extends AbstractModelList
{
    protected function urlPrefix(): string
    {
        return 'ul_';
    }

    protected function getDataClass(): string
    {
        return UserData::class;
    }

    protected function getBaseQuery(): Builder
    {
        $userModel = config('cms.models.user');

        return $userModel::query();
    }

    protected function getTable(): Table
    {
        return Table::make()
            ->columns([
                Id::make(),
                PersonName::make('personName')
                    ->label(__('Name'))
                    ->width('w-2/3')
                    ->modal(),
                Text::make('email')->label(__('Email')),
                Ago::make('updatedAt')->label(__('Last Changed')),
            ])
            ->defaultSort('forename', 'asc')
            ->sortable(['id', 'forename', 'email', 'updated_at'])
            ->filters([
                TableFilter::callback('search', function (Builder $query, mixed $value): void {
                    $query->where(function (Builder $q) use ($value): void {
                        $q->where('forename', 'like', '%'.$value.'%')
                            ->orWhere('surname', 'like', '%'.$value.'%')
                            ->orWhere('email', 'like', '%'.$value.'%');
                    });
                })
                    ->field(
                        TextField::make('search')
                            ->label(__('Search'))
                            ->placeholder(__('Search users...'))
                    ),
            ]);
    }
}
