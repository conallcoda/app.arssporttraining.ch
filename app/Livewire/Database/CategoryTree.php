<?php

namespace App\Livewire\Database;

use App\Actions\DeleteCategoryTree as DeleteAction;
use App\Cms\Data\AbstractData;
use App\Cms\Form\Action;
use App\Cms\Livewire\AbstractModelTree;
use App\Data\Category\CategoryData;
use App\Models\Tag;
use Flux\Flux;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\View\View;
use Livewire\Attributes\Computed;

class CategoryTree extends AbstractModelTree
{
    public ?string $confirmDescription = null;

    public ?string $selectedTab = null;

    public string $rootCategoryName = '';

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

    protected function getSortActions(): array
    {
        return [];
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

    public function handleFormSubmitted(array $data): void
    {
        $isNew = empty($data['id']);

        $dto = $this->createDataFromForm($data);
        $dto->persist();

        $name = $data['name'] ?? $this->getEntityName();
        $action = $isNew ? 'created' : 'updated';
        Flux::toast(text: "{$name} {$action}", variant: 'success');

        $this->normalizeAlphabeticalSortOrder($dto->parentId);

        if ($isNew && $dto->parentId === null) {
            $this->selectedTab = (string) $dto->id;
        }

        $this->edit = null;
        unset($this->treeItems, $this->flatTreeItems, $this->filteredFlatTreeItems, $this->rootCategories, $this->selectedRootName);
        $this->refreshKey++;
    }

    #[Computed(persist: false)]
    public function treeItems(): array
    {
        $roots = $this->getRootsQuery()
            ->with(['children' => fn ($q) => $q->orderBy('name')])
            ->orderBy('name')
            ->get();

        $this->eagerLoadNestedChildren($roots);

        return $roots
            ->map(fn (Model $model) => $this->dataFromModel($model))
            ->all();
    }

    protected function eagerLoadNestedChildren($items): void
    {
        $children = new \Illuminate\Database\Eloquent\Collection(
            $items->pluck('children')->flatten()->all()
        );

        if ($children->isEmpty()) {
            return;
        }

        $children->load(['children' => fn ($q) => $q->orderBy('name')]);
        $this->eagerLoadNestedChildren($children);
    }

    public function mount(): void
    {
        parent::mount();

        $firstRoot = $this->getRootsQuery()->orderBy('name')->first();
        $this->selectedTab = $firstRoot ? (string) $firstRoot->id : null;
    }

    #[Computed]
    public function rootCategories(): array
    {
        return $this->getRootsQuery()
            ->orderBy('name')
            ->get()
            ->map(fn (Tag $tag) => ['id' => $tag->id, 'name' => $tag->name])
            ->all();
    }

    #[Computed]
    public function selectedRootName(): ?string
    {
        if (! $this->selectedTab) {
            return null;
        }

        $selectedId = (int) $this->selectedTab;

        return collect($this->rootCategories)
            ->firstWhere('id', $selectedId)['name'] ?? null;
    }

    public function openRenameRoot(): void
    {
        if (! $this->selectedTab) {
            return;
        }

        $this->rootCategoryName = $this->selectedRootName ?? '';

        Flux::modal('rename-root-category')->show();
    }

    public function saveRenameRoot(): void
    {
        $this->validate([
            'rootCategoryName' => 'required|string|max:255',
        ]);

        $tag = $this->getBaseQuery()->findOrFail((int) $this->selectedTab);
        $tag->name = $this->rootCategoryName;
        $tag->save();

        $this->rootCategoryName = '';

        Flux::modal('rename-root-category')->close();

        $this->normalizeAlphabeticalSortOrder(null);

        unset($this->treeItems, $this->flatTreeItems, $this->filteredFlatTreeItems, $this->rootCategories, $this->selectedRootName);
        $this->refreshKey++;
    }

    public function confirmDeleteRoot(): void
    {
        if (! $this->selectedTab) {
            return;
        }

        $this->confirmAction('delete', (int) $this->selectedTab);
    }

    public function removeItem(int $id): void
    {
        $tag = $this->getBaseQuery()->findOrFail($id);
        $name = $tag->name ?? $this->getEntityName();
        $parentId = $tag->parent_id;
        $wasRoot = $parentId === null;

        $deleteAction = new DeleteAction;
        $deleteAction->execute($tag);

        Flux::toast(text: "{$name} deleted", variant: 'success');

        $this->normalizeAlphabeticalSortOrder($parentId);

        unset($this->treeItems, $this->flatTreeItems, $this->filteredFlatTreeItems, $this->rootCategories, $this->selectedRootName);
        $this->refreshKey++;

        if ($wasRoot) {
            $firstRoot = $this->getRootsQuery()->orderBy('name')->first();
            $this->selectedTab = $firstRoot ? (string) $firstRoot->id : null;
        }
    }

    #[Computed(persist: false)]
    public function filteredFlatTreeItems(): array
    {
        if (! $this->selectedTab) {
            return [];
        }

        $selectedId = (int) $this->selectedTab;

        $selectedRoot = collect($this->treeItems)
            ->first(fn (CategoryData $item) => $item->id === $selectedId);

        if (! $selectedRoot || empty($selectedRoot->children)) {
            return [];
        }

        return $this->flattenTree($selectedRoot->children, []);
    }

    public function render(): View
    {
        return view('livewire.database.category-tree', [
            'entityName' => $this->getEntityName(),
            'entitySlug' => $this->getEntitySlug(),
        ]);
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
