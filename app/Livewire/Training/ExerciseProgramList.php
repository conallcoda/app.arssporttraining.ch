<?php

namespace App\Livewire\Training;

use App\Data\Training\ExerciseProgramData;
use App\Form\Fields\OwnerFilter;
use App\Livewire\Concerns\ClearsOwnerFilterOnTabSwitch;
use App\Livewire\Concerns\SwitchesToVisibleTabAfterCreate;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramTypeEnum;
use App\Models\Tag;
use Coda\Cms\Display\DisplayFields\Ago;
use Coda\Cms\Display\DisplayFields\Badge;
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
use Flux\Flux;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ExerciseProgramList extends AbstractModelList
{
    use ClearsOwnerFilterOnTabSwitch;
    use SwitchesToVisibleTabAfterCreate {
        handleFormSubmitted as handleStandardFormSubmitted;
    }

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
            ->select('exercise_programs.*');
    }

    protected function buildItemsQuery()
    {
        $query = parent::buildItemsQuery();

        if ($this->sort === '') {
            $query->orderBy(
                Tag::query()
                    ->select('name')
                    ->whereColumn('tags.id', 'exercise_programs.exercise_category_id')
                    ->limit(1)
            )
                ->orderBy('exercise_programs.name');
        }

        return $query;
    }

    protected function getTable(): Table
    {
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
                View::make('name', ExerciseProgramView::class)->label(__('Name'))->width('w-[14rem]'),
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
                    ->label(__('Owner'))
                    ->source(fn (ExerciseProgramData $data) => [
                        [
                            'label' => $data->ownerName ?? __('No owner'),
                            'color' => $data->ownerColor,
                            'modalField' => 'owner_id',
                        ],
                    ]),
                Badge::make('exerciseCategoryName')
                    ->label(__('Category'))
                    ->sortAs('category')
                    ->source(fn (ExerciseProgramData $data) => $data->exerciseCategoryName === null ? [] : [[
                        'label' => $data->exerciseCategoryName,
                        'color' => $data->exerciseCategoryColor,
                        'modalField' => 'exercise_category_id',
                    ]]),
                Relationship::make('exercises')->label(__('Exercises'))->modal()->width('w-full'),
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
                $query->where(function (Builder $query) use ($value): void {
                    $query->where('exercise_programs.name', 'like', '%'.$value.'%')
                        ->orWhereHas('exerciseCategory', fn (Builder $categoryQuery) => $categoryQuery
                            ->where('tags.name', 'like', '%'.$value.'%'));
                });
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
            $filters[] = TableFilter::callback('owner', function (Builder $query, mixed $value): void {
                $query->whereIn('exercise_programs.owner_id', (array) $value);
            })
                ->field(new OwnerFilter('owner'));
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

    protected function getExtraActions(): array
    {
        return [
            ...parent::getExtraActions(),
            Action::make('duplicate', __('Duplicate'))
                ->rowMenu()
                ->icon('copy')
                ->handler('openDuplicateProgram'),
        ];
    }

    public function openDuplicateProgram(int $programId): void
    {
        $program = $this->getBaseQuery()->findOrFail($programId);
        $data = ExerciseProgramData::fromModel($program)->toArray();
        $addAction = $this->getAddAction();
        $modalName = $addAction !== null
            ? $this->getModalNameForAction($addAction)
            : $this->editModalName;

        $this->dispatch("open-{$modalName}", data: [
            ...$data,
            'id' => null,
            'name' => '',
            '_duplicate_source_program_id' => $program->id,
        ], title: __('Duplicate Program'), focusField: 'name');
    }

    public function handleFormSubmitted(array $data): void
    {
        if (! empty($data['_duplicate_source_program_id'])) {
            $this->handleDuplicateProgramSubmitted($data);

            return;
        }

        $this->handleStandardFormSubmitted($data);
    }

    protected function handleDuplicateProgramSubmitted(array $data): void
    {
        $sourceProgramId = (int) ($data['_duplicate_source_program_id'] ?? 0);

        if ($sourceProgramId <= 0) {
            return;
        }

        $clone = DB::transaction(function () use ($sourceProgramId, $data): ExerciseProgram {
            $sourceProgram = $this->getBaseQuery()->findOrFail($sourceProgramId);
            $clone = $sourceProgram->duplicate();

            $clone->update([
                'name' => $data['name'] ?? '',
            ]);
            $clone->tags()->sync($sourceProgram->internalTags()->pluck('tags.id')->all());

            return $clone;
        });

        Flux::toast(text: "{$clone->name} created", variant: 'success');

        $listContext = $this->resolveCreatedItemListContext((int) $clone->id);

        if ($listContext !== null) {
            $this->selectedTab = $listContext['selectedTab'];
            $this->filters = $listContext['filters'];
            $this->setPage($listContext['page'], pageName: $this->prefixedPageName());
        }

        $this->edit = null;
        $this->resetState();
        $this->refreshKey++;
        $this->emit();
    }
}
