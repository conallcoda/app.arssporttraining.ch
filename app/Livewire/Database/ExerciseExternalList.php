<?php

namespace App\Livewire\Database;

use App\Cms\Data\AbstractData;
use App\Cms\Display\DisplayFields\Ago;
use App\Cms\Display\DisplayFields\Breadcrumb;
use App\Cms\Display\DisplayFields\Id;
use App\Cms\Display\DisplayFields\TextWithBadgeGroups;
use App\Cms\Display\Table;
use App\Cms\Display\TableFilter;
use App\Cms\Form\Action;
use App\Cms\Form\Fields\Category as CategoryField;
use App\Cms\Form\Fields\Pillbox as PillboxField;
use App\Cms\Form\Fields\Text as TextField;
use App\Cms\Livewire\AbstractModelList;
use App\Data\Exercise\ExerciseExternalData;
use App\Data\Exercise\ExerciseImportData;
use App\Models\Exercise\ExerciseExternal;
use App\Models\Tag;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ExerciseExternalList extends AbstractModelList
{
    protected function getDataClass(): string
    {
        return ExerciseExternalData::class;
    }

    protected function getBaseQuery(): Builder
    {
        return ExerciseExternal::query()->with(['category.ancestorsAndSelf', 'equipment', 'modifiers']);
    }

    protected function dataFromModel(Model $model): AbstractData
    {
        return ExerciseExternalData::fromExerciseExternal($model);
    }

    protected function createDataFromForm(array $formData): AbstractData
    {
        return ExerciseExternalData::from($formData);
    }

    protected function getActions(): array
    {
        return [
            Action::make('preview', 'Preview')
                ->row()
                ->icon('eye')
                ->alpineEvent('open-youtube-player', fn (ExerciseExternalData $data) => ['url' => $data->videoUrl])
                ->visibleWhen(fn (ExerciseExternalData $data) => self::isYouTubeUrl($data->videoUrl)),
            $this->getEditAction(),
            Action::make('import', 'Import')
                ->row()
                ->icon('upload')
                ->formModal(ExerciseImportData::class, 'Import Exercise', 'Import')
                ->formComponent('database.exercise-form')
                ->handler('handleImportSubmitted')
                ->prepareData(fn (ExerciseExternal $model) => [
                    'externalId' => $model->id,
                    'name' => $model->name,
                    'category' => $model->category_id,
                    'equipment' => $model->equipment->pluck('id')->all(),
                    'modifiers' => $model->modifiers->pluck('id')->all(),
                    'videoUrl' => $model->video_url,
                ]),
        ];
    }

    public function handleImportSubmitted(array $data): void
    {
        $importData = ExerciseImportData::from($data);
        $importData->persist();

        $this->resetState();
        $this->refreshKey++;
    }

    private static function isYouTubeUrl(?string $url): bool
    {
        if ($url === null || $url === '') {
            return false;
        }

        return (bool) preg_match('/(?:youtube\.com\/watch\?.*v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/', $url);
    }

    protected function getTable(): Table
    {
        $tagNames = Tag::query()
            ->whereIn('scope', ['exercise_category', 'exercise_equipment', 'exercise_modifiers'])
            ->pluck('name', 'id');

        $tagBadges = fn (ExerciseExternalData $data, string $field) => collect($data->{$field})
            ->map(fn (int $id) => ['label' => $tagNames[$id] ?? '?'])
            ->all();

        return Table::make()
            ->columns([
                Id::make(),
                TextWithBadgeGroups::make('name')
                    ->label('Exercise')
                    ->modal()
                    ->badgeGroup(
                        'equipment',
                        fn (ExerciseExternalData $data) => $tagBadges($data, 'equipment'),
                        'blue',
                    )
                    ->badgeGroup(
                        'modifiers',
                        fn (ExerciseExternalData $data) => $tagBadges($data, 'modifiers'),
                        '',
                    ),
                Breadcrumb::make('categoryPath')
                    ->label('Category')
                    ->source(fn (ExerciseExternalData $data) => $data->categoryPath),
                Ago::make('updatedAt')->label('Last Changed'),
            ])
            ->sortable(['id', 'updatedAt'])
            ->filters([
                TableFilter::callback('search', function (Builder $query, mixed $value): void {
                    $terms = array_filter(explode(' ', trim($value)));

                    $query->where(function (Builder $outer) use ($terms): void {
                        foreach ($terms as $term) {
                            $outer->orWhere(function (Builder $q) use ($term): void {
                                $q->where('name', 'like', '%'.$term.'%')
                                    ->orWhereHas('category', fn (Builder $q) => $q->where('name', 'like', '%'.$term.'%'))
                                    ->orWhereHas('equipment', fn (Builder $q) => $q->where('name', 'like', '%'.$term.'%'))
                                    ->orWhereHas('modifiers', fn (Builder $q) => $q->where('name', 'like', '%'.$term.'%'));
                            });
                        }
                    });
                })
                    ->field(
                        TextField::make('search')
                            ->label('Search')
                    ),
                TableFilter::callback('category', function (Builder $query, mixed $value): void {
                    if ($value === '' || $value === null) {
                        return;
                    }

                    $descendantIds = Tag::findOrFail($value)
                        ->descendantsAndSelf()
                        ->pluck('id');

                    $query->whereIn('category_id', $descendantIds);
                })
                    ->field(
                        CategoryField::make('category', 'exercise_category')
                            ->label('Category')
                            ->withOptions()
                    ),
                TableFilter::callback('equipment', function (Builder $query, mixed $value): void {
                    $ids = is_array($value) ? array_filter($value) : [];

                    if (empty($ids)) {
                        return;
                    }

                    $query->whereHas('equipment', fn (Builder $q) => $q->whereIn('tags.id', $ids));
                })
                    ->field(
                        PillboxField::make('equipment')
                            ->label('Equipment')
                            ->options(
                                Tag::query()
                                    ->forScope('exercise_equipment')
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all()
                            )
                    ),
                TableFilter::callback('modifiers', function (Builder $query, mixed $value): void {
                    $ids = is_array($value) ? array_filter($value) : [];

                    if (empty($ids)) {
                        return;
                    }

                    $query->whereHas('modifiers', fn (Builder $q) => $q->whereIn('tags.id', $ids));
                })
                    ->field(
                        PillboxField::make('modifiers')
                            ->label('Modifiers')
                            ->options(
                                Tag::query()
                                    ->forScope('exercise_modifiers')
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all()
                            )
                    ),
            ]);
    }
}
