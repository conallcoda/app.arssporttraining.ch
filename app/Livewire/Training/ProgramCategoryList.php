<?php

namespace App\Livewire\Training;

use App\Data\Training\ExerciseProgramCategoryData;
use App\Models\Exercise\ExerciseProgramCategory;
use Coda\Cms\Display\DisplayFields\ColorBadge;
use Coda\Cms\Display\DisplayFields\Id;
use Coda\Cms\Display\DisplayFields\Text;
use Coda\Cms\Display\Table;
use Coda\Cms\Display\TableFilter;
use Coda\Cms\Form\Fields\Text as TextField;
use Coda\Cms\Livewire\AbstractModelList;
use Coda\Cms\Support\ColorPalette;
use Illuminate\Contracts\Database\Eloquent\Builder;

class ProgramCategoryList extends AbstractModelList
{
    protected function urlPrefix(): string
    {
        return 'pcl_';
    }

    protected function getDataClass(): string
    {
        return ExerciseProgramCategoryData::class;
    }

    protected function getBaseQuery(): Builder
    {
        return ExerciseProgramCategory::query();
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
                ColorBadge::make('color')
                    ->label('Color')
                    ->colorLabels(ColorPalette::COLORS),
            ])
            ->sortable(['id', 'name'])
            ->defaultSort('name', 'asc')
            ->filters([
                TableFilter::callback('search', function (Builder $query, mixed $value): void {
                    $query->where('name', 'like', '%'.$value.'%');
                })
                    ->field(
                        TextField::make('search')
                            ->label('Search')
                            ->placeholder('Search categories...')
                    ),
            ]);
    }
}
