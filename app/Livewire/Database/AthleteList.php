<?php

namespace App\Livewire\Database;

use App\Data\Athlete\AthleteData;
use App\Models\Users\User;
use App\Models\Users\UserTypeEnum;
use Coda\Cms\Display\DisplayFields\Ago;
use Coda\Cms\Display\DisplayFields\Id;
use Coda\Cms\Display\DisplayFields\Text;
use Coda\Cms\Display\Table;
use Coda\Cms\Display\TableFilter;
use Coda\Cms\Form\Fields\Text as TextField;
use Coda\Cms\Livewire\AbstractModelList;
use Illuminate\Contracts\Database\Eloquent\Builder;

class AthleteList extends AbstractModelList
{
    protected function urlPrefix(): string
    {
        return 'al_';
    }

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
            ])
            ->filters([
                TableFilter::callback('search', function (Builder $query, mixed $value): void {
                    $query->where(function (Builder $q) use ($value): void {
                        $q->where('forename', 'like', '%'.$value.'%')
                            ->orWhere('surname', 'like', '%'.$value.'%');
                    });
                })
                    ->field(
                        TextField::make('search')
                            ->label('Search')
                            ->placeholder('Search athletes...')
                    ),
            ]);
    }
}
