<?php

namespace App\Livewire\Training;

use App\Data\Training\ExercisePlanData;
use App\Models\Exercise\ExercisePlan;
use Coda\Cms\Display\DisplayFields\Id;
use Coda\Cms\Display\DisplayFields\View;
use Coda\Cms\Display\Table;
use Coda\Cms\Display\TableFilter;
use Coda\Cms\Form\Action;
use Coda\Cms\Form\DuplicateNameForm;
use Coda\Cms\Form\Fields\Text as TextField;
use Coda\Cms\Livewire\AbstractModelList;
use Illuminate\Contracts\Database\Eloquent\Builder;

class ExercisePlanList extends AbstractModelList
{
    protected function urlPrefix(): string
    {
        return 'epl_';
    }

    protected function getEntityName(): string
    {
        return 'Plan';
    }

    protected function getDataClass(): string
    {
        return ExercisePlanData::class;
    }

    protected function getBaseQuery(): Builder
    {
        return ExercisePlan::query();
    }

    protected function getTable(): Table
    {
        return Table::make()
            ->columns([
                Id::make(),
                View::make('name', ExercisePlanView::class)->label(__('Name')),
            ])
            ->sortable(['id', 'name'])
            ->defaultSort('name', 'asc')
            ->filters([
                TableFilter::callback('search', function (Builder $query, mixed $value): void {
                    $query->where('name', 'like', '%'.$value.'%');
                })
                    ->field(
                        TextField::make('search')
                            ->label(__('Search'))
                            ->placeholder(__('Search plans...'))
                    ),
            ])
            ->actions([
                Action::make('duplicate', __('Duplicate'))
                    ->rowMenu()
                    ->icon('copy')
                    ->formModal(DuplicateNameForm::class, __('Duplicate').' '.$this->getEntityName(), __('Duplicate'))
                    ->handler('handleDuplicateSubmitted')
                    ->prepareData(fn (ExercisePlan $model) => [
                        'id' => $model->id,
                        'name' => ($model->name ?? '').__(' (Copy)'),
                    ]),
            ]);
    }

    public function handleDuplicateSubmitted(array $data): void
    {
        if (empty($data['id'])) {
            return;
        }

        $original = ExercisePlan::findOrFail($data['id']);

        $newTemplate = $original->replicate();
        $newTemplate->name = $data['name'] ?? $original->name.__(' (Copy)');
        $newTemplate->save();

        $this->resetState();
        $this->refreshKey++;
    }
}
