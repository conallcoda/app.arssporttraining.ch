<?php

namespace Coda\Cms\Livewire;

use Coda\Cms\Data\AbstractData;
use Coda\Cms\Form\Action;
use Coda\Cms\Form\Form;
use Flux\Flux;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

abstract class AbstractModelTree extends Component
{
    use Concerns\InteractsWithCrudActions;
    use Concerns\InteractsWithEntityDefinition;
    use Concerns\InteractsWithFormData;
    use Concerns\WithUrlPrefix;

    public array $options = [];

    public array $data = [];

    public ?int $confirmingId = null;

    public ?string $confirmingAction = null;

    public ?int $edit = null;

    public int $refreshKey = 0;

    public ?string $confirmDescription = null;

    abstract protected function getDataClass(): string;

    abstract protected function getBaseQuery(): Builder;

    abstract protected function getRootsQuery(): Builder;

    protected function getSortActions(): array
    {
        return [
            Action::make('moveUp', '')
                ->row()
                ->icon('chevron-up')
                ->disabledWhen('first'),
            Action::make('moveDown', '')
                ->row()
                ->icon('chevron-down')
                ->disabledWhen('last'),
        ];
    }

    #[Computed(persist: false)]
    public function treeItems(): array
    {
        $roots = $this->getRootsQuery()
            ->with(['children' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
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

        $children->load(['children' => fn ($q) => $q->orderBy('sort_order')]);
        $this->eagerLoadNestedChildren($children);
    }

    #[Computed(persist: false)]
    public function flatTreeItems(): array
    {
        return $this->flattenTree($this->treeItems, []);
    }

    protected function flattenTree(array $items, array $ancestorIds): array
    {
        $result = [];
        $count = count($items);

        foreach ($items as $index => $item) {
            $item->ancestorIds = $ancestorIds;
            $item->isFirstSibling = ($index === 0);
            $item->isLastSibling = ($index === $count - 1);
            $result[] = $item;

            if (! empty($item->children)) {
                $childAncestorIds = array_merge($ancestorIds, [$item->id]);
                $result = array_merge($result, $this->flattenTree($item->children, $childAncestorIds));
            }
        }

        return $result;
    }

    public function handleFormSubmitted(array $data): void
    {
        $isNew = empty($data['id']);

        $dto = $this->createDataFromForm($data);
        $dto->persist();

        $name = $data['name'] ?? $this->getEntityName();
        $action = $isNew ? 'created' : 'updated';
        Flux::toast(text: "{$name} {$action}", variant: 'success');

        if ($isNew) {
            $maxSort = $this->getBaseQuery()
                ->where('parent_id', $dto->parentId ?? null)
                ->where('id', '!=', $dto->id)
                ->max('sort_order') ?? -1;

            $model = $this->getBaseQuery()->findOrFail($dto->id);
            $model->sort_order = $maxSort + 1;
            $model->save();
        }

        $this->edit = null;
        unset($this->treeItems, $this->flatTreeItems);
        $this->refreshKey++;
        $this->emit();
    }

    public function removeItem(int $id): void
    {
        $model = $this->getBaseQuery()->findOrFail($id);
        $name = $model->name ?? $this->getEntityName();
        $model->delete();

        Flux::toast(text: "{$name} deleted", variant: 'success');

        unset($this->treeItems, $this->flatTreeItems);
        $this->refreshKey++;
        $this->emit();
    }

    public function moveUp(int $id): void
    {
        $item = $this->getBaseQuery()->findOrFail($id);
        $currentSort = $item->sort_order;

        if ($currentSort <= 0) {
            return;
        }

        $previousSibling = $this->getBaseQuery()
            ->where('parent_id', $item->parent_id)
            ->where('sort_order', $currentSort - 1)
            ->first();

        if ($previousSibling) {
            $previousSibling->sort_order = $currentSort;
            $previousSibling->save();
        }

        $item->sort_order = $currentSort - 1;
        $item->save();

        unset($this->treeItems, $this->flatTreeItems);
        $this->refreshKey++;
        $this->emit();
    }

    public function moveDown(int $id): void
    {
        $item = $this->getBaseQuery()->findOrFail($id);
        $currentSort = $item->sort_order;
        $maxSort = $this->getBaseQuery()
            ->where('parent_id', $item->parent_id)
            ->max('sort_order');

        if ($currentSort >= $maxSort) {
            return;
        }

        $nextSibling = $this->getBaseQuery()
            ->where('parent_id', $item->parent_id)
            ->where('sort_order', $currentSort + 1)
            ->first();

        if ($nextSibling) {
            $nextSibling->sort_order = $currentSort;
            $nextSibling->save();
        }

        $item->sort_order = $currentSort + 1;
        $item->save();

        unset($this->treeItems, $this->flatTreeItems);
        $this->refreshKey++;
        $this->emit();
    }

    protected function normalizeSortOrder(?int $parentId): void
    {
        $siblings = $this->getBaseQuery()
            ->where('parent_id', $parentId)
            ->orderBy('sort_order')
            ->get();

        foreach ($siblings as $index => $sibling) {
            if ($sibling->sort_order !== $index) {
                $sibling->sort_order = $index;
                $sibling->save();
            }
        }
    }

    protected function getEntitySlug(): string
    {
        return Str::of(class_basename($this))
            ->replaceLast('Tree', '')
            ->snake()
            ->slug()
            ->toString();
    }

    protected function getEntityName(): string
    {
        return Str::of(class_basename($this))
            ->replaceLast('Tree', '')
            ->headline()
            ->toString();
    }

    protected function urlProperties(): array
    {
        return [
            'edit' => ['except' => null],
        ];
    }

    protected function getDefaultOptions(): array
    {
        return [
            'showAddButton' => true,
        ];
    }

    public function mount(): void
    {
        $this->mountEntityDefaults();

        if ($this->edit !== null) {
            $this->openEditFromUrl();
        }
    }

    public function render(): View
    {
        return view('cms::model-tree', [
            'entityName' => $this->getEntityName(),
            'entitySlug' => $this->getEntitySlug(),
            'options' => $this->options,
        ]);
    }
}
