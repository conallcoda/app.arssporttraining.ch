<?php

namespace App\Livewire\Database;

use App\Actions\DeleteCategoryTree as DeleteAction;
use App\Cms\Data\AbstractData;
use App\Cms\Form\Action;
use App\Cms\Livewire\AbstractModelTree;
use App\Data\Category\CategoryData;
use App\Models\Tag;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CategoryTree extends AbstractModelTree
{
    public ?string $confirmDescription = null;

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

    protected function getExtraActions(): array
    {
        return [
            Action::make('addChild', 'Add Child')
                ->rowMenu()
                ->icon('plus')
                ->formModal(CategoryData::class, 'Add Child Category')
                ->prepareData(fn (Tag $model, array $data) => [
                    'id' => null,
                    'parentId' => $model->id,
                ])
                ->handler('handleFormSubmitted'),
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
                $childWord = $descendantCount === 1 ? 'child category' : 'child categories';
                $lines[] = "This category has {$descendantCount} {$childWord} that will also be deleted.";
            }

            if ($exerciseCount > 0) {
                $exerciseWord = $exerciseCount === 1 ? 'exercise' : 'exercises';
                $scope = $descendantCount > 0 ? ' (including children)' : '';
                $lines[] = "It is currently assigned to {$exerciseCount} {$exerciseWord}{$scope}. Deleting will unassign them.";
            }

            $lines[] = 'This action cannot be reversed.';

            $this->confirmDescription = implode("\n", $lines);
        }

        parent::confirmAction($actionName, $id);
    }

    public function removeItem(int $id): void
    {
        $tag = $this->getBaseQuery()->findOrFail($id);
        $parentId = $tag->parent_id;

        $deleteAction = new DeleteAction;
        $deleteAction->execute($tag);

        $this->normalizeSortOrder($parentId);

        unset($this->treeItems, $this->flatTreeItems);
        $this->refreshKey++;
        $this->emit();
    }
}
