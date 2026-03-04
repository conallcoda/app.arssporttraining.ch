<?php

namespace App\Livewire\Database;

use App\Data\Exercise\ExerciseData;
use App\Models\Exercise\Exercise;
use App\Models\Tag;
use Coda\Cms\Data\AbstractData;
use Coda\Cms\Display\DisplayFields\Ago;
use Coda\Cms\Display\DisplayFields\Badge;
use Coda\Cms\Display\DisplayFields\ColorBadge;
use Coda\Cms\Display\DisplayFields\Id;
use Coda\Cms\Display\DisplayFields\TextWithBadgeGroups;
use Coda\Cms\Display\Table;
use Coda\Cms\Display\TableFilter;
use Coda\Cms\Form\Action;
use Coda\Cms\Form\Fields\Text as TextField;
use Coda\Cms\Livewire\AbstractModelList;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ExerciseList extends AbstractModelList
{
    protected function urlPrefix(): string
    {
        return 'el_';
    }

    protected function getDataClass(): string
    {
        return ExerciseData::class;
    }

    protected function getBaseQuery(): Builder
    {
        return Exercise::query()->with(['category', 'equipment', 'modifiers', 'media']);
    }

    protected function dataFromModel(Model $model): AbstractData
    {
        return ExerciseData::fromExercise($model);
    }

    protected function createDataFromForm(array $formData): AbstractData
    {
        return ExerciseData::from($formData);
    }

    protected function getTable(): Table
    {
        $tagNames = Tag::query()
            ->whereIn('scope', ['exercise_category', 'exercise_equipment', 'exercise_modifiers'])
            ->pluck('name', 'id');

        $exerciseCategoryColorLabels = Tag::query()
            ->forScope('exercise_category')
            ->whereNull('parent_id')
            ->pluck('name', 'color')
            ->all();

        $tagBadges = fn (ExerciseData $data, string $field) => collect($data->{$field})
            ->map(fn (int $id) => ['label' => $tagNames[$id] ?? '?', 'modalField' => $field])
            ->all();

        return Table::make()
            ->columns([
                Id::make(),
                TextWithBadgeGroups::make('name')
                    ->label('Exercise')
                    ->modal()
                    ->badgeGroup(
                        'equipment',
                        fn (ExerciseData $data) => $tagBadges($data, 'equipment'),
                        'blue',
                    )
                    ->badgeGroup(
                        'modifiers',
                        fn (ExerciseData $data) => $tagBadges($data, 'modifiers'),
                        '',
                    ),
                ColorBadge::make('categoryColor')
                    ->label('Category')
                    ->colorLabels($exerciseCategoryColorLabels),
                Badge::make('settings')
                    ->label('Settings')
                    ->source(fn (ExerciseData $data) => $data->getDefaultsBadges()),
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
                            ->placeholder('Search exercises...')
                    ),
            ]);
    }

    protected function getActions(): array
    {
        return array_filter([
            Action::make('preview', 'Preview')
                ->row()
                ->icon('eye')
                ->alpineEvent('open-youtube-player', fn (ExerciseData $data) => ['url' => $data->videoUrl])
                ->visibleWhen(fn (ExerciseData $data) => $data->videoUrl !== null && $data->videoUrl !== ''),
            $this->getAddAction(),
            $this->getEditAction(),
            $this->getDeleteAction(),
        ]);
    }

    protected function getAddAction(): ?Action
    {
        return parent::getAddAction()
            ?->formComponent('database.exercise-form');
    }

    protected function getEditAction(): Action
    {
        return parent::getEditAction()
            ->formComponent('database.exercise-form');
    }
}
