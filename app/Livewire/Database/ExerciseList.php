<?php

namespace App\Livewire\Database;

use App\Data\Exercise\ExerciseData;
use App\Form\Fields\CoachFilter;
use App\Livewire\Concerns\ClearsCoachFilterOnTabSwitch;
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
use Coda\Cms\Livewire\AbstractModelList;
use Coda\Cms\Support\OwnershipTabs;
use Coda\FormKit\Action;
use Coda\FormKit\Fields\Pillbox;
use Coda\FormKit\Fields\Text as TextField;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ExerciseList extends AbstractModelList
{
    use ClearsCoachFilterOnTabSwitch;

    protected function urlPrefix(): string
    {
        return 'el_';
    }

    protected function getDataClass(): string
    {
        return ExerciseData::class;
    }

    protected function getTabs(): array
    {
        return OwnershipTabs::make('Exercises')->toArray();
    }

    protected function getDefaultTabKey(): ?string
    {
        return OwnershipTabs::make('Exercises')->defaultTab($this->getBaseQuery());
    }

    protected function getBaseQuery(): Builder
    {
        return Exercise::query()
            ->select('exercises.*')
            ->with(['category', 'equipment', 'modifiers', 'internalTags', 'media', 'owner']);
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
            ->whereIn('scope', ['exercise_category', 'exercise_equipment', 'exercise_modifiers', 'exercise_internal'])
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
                    ->label(__('Exercise'))
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
                Badge::make('coach')
                    ->label(__('Coach'))
                    ->source(fn (ExerciseData $data) => [
                        [
                            'label' => $data->ownerName ?? __('Unassigned'),
                            'color' => $data->ownerColor,
                            'modalField' => 'owner_id',
                        ],
                    ]),
                ColorBadge::make('categoryColor')
                    ->label(__('Category'))
                    ->sortAs('category')
                    ->colorLabels($exerciseCategoryColorLabels),
                Badge::make('internalTags')
                    ->label(__('Tags'))
                    ->source(fn (ExerciseData $data) => collect($data->internalTags)
                        ->map(fn (int $id) => ['label' => $tagNames[$id] ?? '?', 'modalField' => 'internalTags'])
                        ->all()
                    ),
                Badge::make('settings')
                    ->label(__('Settings'))
                    ->source(fn (ExerciseData $data) => $data->getDefaultsBadges()),
                Ago::make('updatedAt')->label(__('Last Changed')),
            ])
            ->sortable(['id', 'name', 'coach', 'category', 'updatedAt'])
            ->filters($this->buildFilters('exercise_internal'));
    }

    private function buildFilters(string $tagScope): array
    {
        $filters = [
            TableFilter::callback('search', function (Builder $query, mixed $value): void {
                $query->where('name', 'like', '%'.$value.'%');
            })
                ->field(
                    TextField::make('search')
                        ->label(__('Search'))
                        ->placeholder(__('Search exercises...'))
                ),
            TableFilter::callback('tags', function (Builder $query, mixed $value): void {
                $query->whereHas('internalTags', fn (Builder $q) => $q->whereIn('tags.id', (array) $value));
            })
                ->field(
                    (new Pillbox('tags'))
                        ->label(__('Tags'))
                        ->placeholder(__('Filter by tags...'))
                        ->options(Tag::query()->forScope($tagScope)->pluck('name', 'id')->all())
                ),
            TableFilter::callback('modifiers', function (Builder $query, mixed $value): void {
                $ids = is_array($value) ? array_filter($value) : [];

                if (empty($ids)) {
                    return;
                }

                $query->whereHas('modifiers', fn (Builder $q) => $q->whereIn('tags.id', $ids));
            })
                ->field(
                    (new Pillbox('modifiers'))
                        ->label(__('Modifiers'))
                        ->placeholder(__('Filter by modifiers...'))
                        ->options(Tag::query()->forScope('exercise_modifiers')->orderBy('name')->pluck('name', 'id')->all())
                ),
        ];

        if ($this->selectedTab === 'all') {
            $filters[] = TableFilter::callback('coach', function (Builder $query, mixed $value): void {
                $query->whereIn('exercises.owner_id', (array) $value);
            })
                ->field(new CoachFilter('coach'));
        }

        return $filters;
    }

    protected function getActions(): array
    {
        return array_filter([
            Action::make('preview', __('Preview'))
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
