<?php

namespace App\Livewire\Training;

use App\Actions\DeleteCategoryTree as DeleteAction;
use App\Data\Training\CategoryData;
use App\Models\Tag;
use Carbon\Carbon;
use Coda\Cms\Data\AbstractData;
use Coda\Cms\Data\TreeColumnData;
use Coda\Cms\Data\TreeNodeTypeData;
use Coda\Cms\Livewire\AbstractModelTree;
use Flux\Flux;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\View\View;

class CategoryList extends AbstractModelTree
{
    protected int $defaultExpand = 0;

    public ?string $confirmDescription = null;

    protected function urlPrefix(): string
    {
        return 'cl_';
    }

    public function render(): View
    {
        return view('livewire.training.category-list', [
            'entityName' => $this->getEntityName(),
            'entitySlug' => $this->getEntitySlug(),
            'options' => $this->options,
        ]);
    }

    protected function getDataClass(): string
    {
        return CategoryData::class;
    }

    protected function getBaseQuery(): Builder
    {
        return Tag::query()->forScope('exercise_category');
    }

    protected function getRootsQuery(): Builder
    {
        return Tag::query()
            ->forScope('exercise_category')
            ->whereNull('parent_id');
    }

    protected function dataFromModel(Model $model): AbstractData
    {
        return CategoryData::fromTagTree($model);
    }

    protected function createDataFromForm(array $formData): AbstractData
    {
        return CategoryData::from($formData);
    }

    protected function usesTypedCreateModal(): bool
    {
        return true;
    }

    protected function getSortActions(): array
    {
        return [];
    }

    protected function getNodeTypes(): array
    {
        return [
            'default' => TreeNodeTypeData::make('default', __('Add Category'), CategoryData::class)
                ->handler('handleFormSubmitted')
                ->modalTitle(__('Add Category'))
                ->submitLabel(__('Save'))
                ->prepareData(fn ($parent) => [
                    'id' => null,
                    'parentId' => $parent?->id,
                ]),
        ];
    }

    protected function getCreateNodeTypeMap(): array
    {
        return [
            'root' => ['default'],
            '*' => ['default'],
        ];
    }

    protected function getTreeColumns(): array
    {
        return [
            TreeColumnData::make('name', __('Name'))
                ->hierarchy()
                ->widthClass('w-1/3'),
            TreeColumnData::make('shortName', __('Short Name'))
                ->valueUsing(fn ($item) => data_get($item->formData, 'parentId') === null ? data_get($item->formData, 'shortName') : null),
            TreeColumnData::make('color', __('Color'))
                ->colorBadge('color'),
            TreeColumnData::make('updatedAt', __('Last Changed'))
                ->widthClass('w-40')
                ->valueUsing(function ($item) {
                    $updatedAt = data_get($item->formData, 'updatedAt');

                    if ($updatedAt instanceof Carbon) {
                        return $updatedAt->diffForHumans();
                    }

                    if (is_string($updatedAt) && $updatedAt !== '') {
                        return Carbon::parse($updatedAt)->diffForHumans();
                    }

                    return null;
                }),
        ];
    }

    public function confirmAction(string $actionName, int $id): void
    {
        if ($actionName === 'delete') {
            $tag = $this->getBaseQuery()->findOrFail($id);
            $deleteAction = new DeleteAction;
            $descendantCount = $deleteAction->getDescendantCount($tag);
            $exerciseCount = $deleteAction->getAffectedExerciseCount($tag);

            $lines = [];

            if ($descendantCount > 0) {
                $childWord = $descendantCount === 1 ? __('child category') : __('child categories');
                $lines[] = "This category has {$descendantCount} {$childWord} that will also be deleted.";
            }

            if ($exerciseCount > 0) {
                $exerciseWord = $exerciseCount === 1 ? __('exercise') : __('exercises');
                $scope = $descendantCount > 0 ? __(' (including children)') : '';
                $lines[] = "It is currently assigned to {$exerciseCount} {$exerciseWord}{$scope}. Deleting will unassign them.";
            }

            $lines[] = __('This action cannot be reversed.');

            $this->confirmDescription = implode("\n", $lines);
        }

        parent::confirmAction($actionName, $id);
    }

    public function handleFormSubmitted(array $data): void
    {
        $isNew = empty($data['id']);

        $dto = $this->createDataFromForm($data);
        $dto->persist();

        $name = $data['name'] ?? $this->getEntityName();
        Flux::toast(
            text: __(':name :action', ['name' => $name, 'action' => $isNew ? 'created' : 'updated']),
            variant: 'success'
        );

        $this->normalizeAlphabeticalSortOrder($dto->parentId);
        $this->edit = null;
        $this->refreshTree();
    }

    protected function buildTreeItems(): array
    {
        $roots = $this->getRootsQuery()
            ->with(['children' => fn ($q) => $q->orderBy('name')])
            ->orderBy('name')
            ->get();

        $this->eagerLoadNestedChildren($roots);

        return $roots
            ->map(fn (Model $model) => $this->mapTreeNode(CategoryData::fromTagTree($model)))
            ->all();
    }

    protected function eagerLoadNestedChildren($items): void
    {
        $children = new Collection(
            $items->pluck('children')->flatten()->all()
        );

        if ($children->isEmpty()) {
            return;
        }

        $children->load(['children' => fn ($q) => $q->orderBy('name')]);
        $this->eagerLoadNestedChildren($children);
    }

    public function removeItem(int $id): void
    {
        $model = $this->getBaseQuery()->findOrFail($id);
        $name = $model->name ?? $this->getEntityName();
        $parentId = $model->parent_id;

        $deleteAction = new DeleteAction;
        $deleteAction->execute($model);

        Flux::toast(text: __(':name deleted', ['name' => $name]), variant: 'success');

        $this->normalizeAlphabeticalSortOrder($parentId);
        $this->refreshTree();
    }

    protected function normalizeAlphabeticalSortOrder(?int $parentId): void
    {
        $siblings = $this->getBaseQuery()
            ->where('parent_id', $parentId)
            ->get()
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        foreach ($siblings as $index => $sibling) {
            if ($sibling->sort_order !== $index) {
                $sibling->sort_order = $index;
                $sibling->save();
            }
        }
    }
}
