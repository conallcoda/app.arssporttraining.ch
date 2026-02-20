<?php

namespace App\Livewire\Database;

use App\Data\Exercise\ExerciseTemplateData;
use App\Models\Exercise\ExerciseTemplate;
use Coda\Cms\Data\AbstractData;
use Coda\Cms\Display\DisplayFields\Ago;
use Coda\Cms\Display\DisplayFields\Badge;
use Coda\Cms\Display\DisplayFields\Id;
use Coda\Cms\Display\DisplayFields\Text;
use Coda\Cms\Display\Table;
use Coda\Cms\Display\TableFilter;
use Coda\Cms\Form\Action;
use Coda\Cms\Form\Fields\Text as TextField;
use Coda\Cms\Livewire\AbstractModelList;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ExerciseTemplateList extends AbstractModelList
{
    protected function urlPrefix(): string
    {
        return 'etl_';
    }

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

    protected function getAddAction(): ?Action
    {
        return parent::getAddAction()
            ?->formComponent('database.exercise-template-form');
    }

    protected function getEditAction(): Action
    {
        return parent::getEditAction()
            ->formComponent('database.exercise-template-form');
    }

    protected function getExtraActions(): array
    {
        return [
            Action::make('duplicate', 'Duplicate')
                ->rowMenu()
                ->icon('copy')
                ->formModal(ExerciseTemplateData::class, 'Duplicate Exercise Template')
                ->formComponent('database.exercise-template-form')
                ->prepareData(function (ExerciseTemplate $model): array {
                    $data = ExerciseTemplateData::fromTemplate($model)->toArray();
                    $data['id'] = null;
                    $data['name'] = $data['name'].' (copy)';

                    return $data;
                })
                ->handler('handleFormSubmitted'),
        ];
    }
}
