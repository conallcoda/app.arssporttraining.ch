<?php

namespace App\Livewire\Database;

use App\Cms\Data\AbstractData;
use App\Cms\Display\DisplayFields\Ago;
use App\Cms\Display\DisplayFields\Badge;
use App\Cms\Display\DisplayFields\Id;
use App\Cms\Display\DisplayFields\Text;
use App\Cms\Display\Table;
use App\Cms\Display\TableFilter;
use App\Cms\Form\Action;
use App\Cms\Form\Fields\Text as TextField;
use App\Cms\Livewire\AbstractModelList;
use App\Data\Exercise\ExerciseTemplateData;
use App\Models\Exercise\ExerciseTemplate;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ExerciseTemplateList extends AbstractModelList
{
    protected function getDataClass(): string
    {
        return ExerciseTemplateData::class;
    }

    protected function getBaseQuery(): Builder
    {
        return ExerciseTemplate::query();
    }

    protected function dataFromModel(Model $model): AbstractData
    {
        return ExerciseTemplateData::fromTemplate($model);
    }

    protected function createDataFromForm(array $formData): AbstractData
    {
        return ExerciseTemplateData::from($formData);
    }

    protected function getTable(): Table
    {
        return Table::make()
            ->columns([
                Id::make(),
                Text::make('name')
                    ->label('Name')
                    ->width('w-1/3')
                    ->modal(),
                Badge::make('defaults')
                    ->label('Defaults')
                    ->source(fn (ExerciseTemplateData $data) => $data->getDefaultsBadges()),
                Ago::make('updatedAt')->label('Last Changed'),
            ])
            ->sortable(['id', 'name', 'updatedAt'])
            ->filters([
                TableFilter::callback('search', function (Builder $query, mixed $value): void {
                    $query->where('name', 'like', '%'.$value.'%');
                })
                    ->field(
                        TextField::make('search')
                            ->label('Search')
                            ->placeholder('Search templates...')
                    ),
            ]);
    }

    protected function getAddAction(): Action
    {
        return parent::getAddAction()
            ->formComponent('database.exercise-template-form');
    }

    protected function getEditAction(): Action
    {
        return parent::getEditAction()
            ->formComponent('database.exercise-template-form');
    }
}
