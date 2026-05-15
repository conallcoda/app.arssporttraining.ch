<?php

namespace App\Livewire\Database;

use App\Actions\DeleteCategoryTree as DeleteAction;
use App\Data\Exercise\ExerciseCategoryData;
use App\Models\Tag;
use Coda\Cms\Data\AbstractData;
use Coda\Cms\Data\TreeNodeTypeData;
use Coda\Cms\Livewire\AbstractModelTree;
use Flux\Flux;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class CategoryTree extends AbstractModelTree
{
    protected function urlPrefix(): string
    {
        return 'ct_';
    }

    public ?string $confirmDescription = null;

    protected function getDataClass(): string
    {
        return ExerciseCategoryData::class;
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
        return ExerciseCategoryData::fromTagTree($model);
    }

    protected function getSortActions(): array
    {
        return [];
    }

    public function usesRootFilter(): bool
    {
        return true;
    }

    public function rootFilterVariant(): string
    {
        return 'segmented';
    }

    public function showSelectedRootHeading(): bool
    {
        return true;
    }

    public function filteredEmptyHeading(): string
    {
        return __('No subcategories yet');
    }

    protected function usesTypedCreateModal(): bool
    {
        return true;
    }

    protected function hideSelectedRootRow(): bool
    {
        return true;
    }

    protected function getNodeTypes(): array
    {
        return [
            'default' => TreeNodeTypeData::make('default', __('Add Child'), ExerciseCategoryData::class)
                ->handler('handleFormSubmitted')
                ->modalTitle(__('Add Child Category'))
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
            1 => ['default'],
            '*' => ['default'],
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
        $action = $isNew ? 'created' : 'updated';
        Flux::toast(text: __(':name :action', ['name' => $name, 'action' => $action]), variant: 'success');

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
            ->map(fn (Model $model) => $this->mapTreeNode(ExerciseCategoryData::fromTagTree($model)))
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
        $tag = $this->getBaseQuery()->findOrFail($id);
        $name = $tag->name ?? $this->getEntityName();
        $parentId = $tag->parent_id;

        $deleteAction = new DeleteAction;
        $deleteAction->execute($tag);

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
