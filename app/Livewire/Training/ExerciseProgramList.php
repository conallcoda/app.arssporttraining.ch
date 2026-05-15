<?php

namespace App\Livewire\Training;

use App\Data\Training\ExerciseProgramData;
use App\Form\Fields\CoachFilter;
use App\Livewire\Concerns\ClearsCoachFilterOnTabSwitch;
use App\Livewire\Concerns\SwitchesToVisibleTabAfterCreate;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramTypeEnum;
use App\Models\Tag;
use Coda\Cms\Display\DisplayFields\Ago;
use Coda\Cms\Display\DisplayFields\Badge;
use Coda\Cms\Display\DisplayFields\ColorBadge;
use Coda\Cms\Display\DisplayFields\Relationship;
use Coda\Cms\Display\DisplayFields\Text;
use Coda\Cms\Display\DisplayFields\View;
use Coda\Cms\Display\Table;
use Coda\Cms\Display\TableFilter;
use Coda\Cms\Livewire\AbstractModelList;
use Coda\Cms\Support\OwnershipTabs;
use Coda\FormKit\Action;
use Coda\FormKit\Fields\Pillbox;
use Coda\FormKit\Fields\Select;
use Coda\FormKit\Fields\Text as TextField;
use Illuminate\Contracts\Database\Eloquent\Builder;

class ExerciseProgramList extends AbstractModelList
{
    use ClearsCoachFilterOnTabSwitch;
    use SwitchesToVisibleTabAfterCreate;

    protected function urlPrefix(): string
    {
        return 'epl_';
    }

    protected function getDataClass(): string
    {
        return ExerciseProgramData::class;
    }

    protected function getTabs(): array
    {
        return OwnershipTabs::make('Programs')->toArray();
    }

    protected function getDefaultTabKey(): ?string
    {
        return OwnershipTabs::make('Programs')->defaultTab($this->getBaseQuery());
    }

    protected function getBaseQuery(): Builder
    {
        return ExerciseProgram::query()
            ->whereNull('exercise_programs.parent_id')
            ->whereNull('exercise_programs.parent_type')
            ->with(['exerciseCategory', 'internalTags', 'owner'])
            ->leftJoin('tags as exercise_category_tags', 'exercise_programs.exercise_category_id', '=', 'exercise_category_tags.id')
            ->select('exercise_programs.*');
    }

    protected function buildItemsQuery()
    {
        $query = parent::buildItemsQuery();

        if ($this->sort === '') {
            $query->orderBy('exercise_category_tags.name')
                ->orderBy('exercise_programs.name');
        }

        return $query;
    }

    protected function getTable(): Table
    {
        $exerciseCategoryColorLabels = Tag::query()
            ->forScope('exercise_category')
            ->whereNull('parent_id')
            ->pluck('name', 'color')
            ->all();

        $tagNames = Tag::query()
            ->forScope('program_internal')
            ->pluck('name', 'id');

        $exerciseCategoryOptions = Tag::query()
            ->forScope('exercise_category')
            ->whereNull('parent_id')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        $programTypeOptions = ExerciseProgramTypeEnum::options();

        return Table::make()
            ->columns([
                Text::make('id')->label(__('ID'))->width('w-16')->prefix('#'),
                View::make('name', ExerciseProgramView::class)->label(__('Name')),
                Badge::make('type')
                    ->label(__('Type'))
                    ->source(fn (ExerciseProgramData $data) => [[
                        'label' => ExerciseProgramTypeEnum::from($data->type)->label(),
                        'color' => match ($data->type) {
                            ExerciseProgramTypeEnum::Program->value => 'blue',
                            ExerciseProgramTypeEnum::WarmUp->value => 'amber',
                            ExerciseProgramTypeEnum::WarmDown->value => 'emerald',
                            default => 'zinc',
                        },
                        'modalField' => 'type',
                    ]]),
                Badge::make('coach')
                    ->label(__('Coach'))
                    ->source(fn (ExerciseProgramData $data) => [
                        [
                            'label' => $data->ownerName ?? __('Unassigned'),
                            'color' => $data->ownerColor,
                            'modalField' => 'owner_id',
                        ],
                    ]),
                ColorBadge::make('exerciseCategoryColor')
                    ->label(__('Category'))
                    ->sortAs('category')
                    ->colorLabels($exerciseCategoryColorLabels),
                Relationship::make('exercises')->label(__('Exercises'))->modal()->width('w-full'),
                Badge::make('internalTags')
                    ->label(__('Tags'))
                    ->source(fn (ExerciseProgramData $data) => collect($data->internalTags)
                        ->map(fn (int $id) => ['label' => $tagNames[$id] ?? '?', 'modalField' => 'internalTags'])
                        ->all()
                    ),
                Ago::make('updatedAt')->label(__('Last Changed')),
            ])
            ->sortable(['id', 'name', 'type', 'coach', 'category', 'updatedAt'])
            ->filters($this->buildFilters($exerciseCategoryOptions, $programTypeOptions))
            ->limit(100);
    }

    private function buildFilters(array $exerciseCategoryOptions, array $programTypeOptions): array
    {
        $filters = [
            TableFilter::callback('search', function (Builder $query, mixed $value): void {
                $query->where('exercise_programs.name', 'like', '%'.$value.'%');
            })
                ->field(
                    TextField::make('search')
                        ->label(__('Search'))
                        ->placeholder(__('Search programs...'))
                ),
            TableFilter::exact('category', 'exercise_category_id')
                ->field(
                    Select::make('category')
                        ->label(__('Category'))
                        ->placeholder(__('All categories'))
                        ->options($exerciseCategoryOptions)
                ),
            TableFilter::exact('type', 'type')
                ->field(
                    Select::make('type')
                        ->label(__('Type'))
                        ->placeholder(__('All types'))
                        ->options($programTypeOptions)
                ),
            TableFilter::callback('tags', function (Builder $query, mixed $value): void {
                $query->whereHas('internalTags', fn (Builder $q) => $q->whereIn('tags.id', (array) $value));
            })
                ->field(
                    (new Pillbox('tags'))
                        ->label(__('Tags'))
                        ->placeholder(__('Filter by tags...'))
                        ->options(Tag::query()->forScope('program_internal')->pluck('name', 'id')->all())
                ),
        ];

        if ($this->selectedTab === 'all') {
            $filters[] = TableFilter::callback('coach', function (Builder $query, mixed $value): void {
                $query->whereIn('exercise_programs.owner_id', (array) $value);
            })
                ->field(new CoachFilter('coach'));
        }

        return $filters;
    }

    protected function getAddAction(): ?Action
    {
        return parent::getAddAction()?->formComponent('training.exercise-program-form-modal');
    }

    protected function getEditAction(): Action
    {
        return parent::getEditAction()->formComponent('training.exercise-program-form-modal');
    }
}
