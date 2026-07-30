<?php

namespace Coda\Cms\Livewire;

use Coda\Cms\Data\AbstractData;
use Coda\Cms\Data\TreeNodeTypeData;
use Coda\Cms\Data\TreeNodeData;
use Coda\FormKit\Action;
use Flux\Flux;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

abstract class AbstractModelTree extends AbstractTree
{
    use Concerns\InteractsWithCrudActions;
    use Concerns\InteractsWithEntityDefinition;
    use Concerns\InteractsWithFormData;
    use Concerns\WithUrlPrefix;

    public array $data = [];

    public ?int $confirmingId = null;

    public ?int $edit = null;

    abstract protected function getDataClass(): string;

    abstract protected function getBaseQuery(): Builder;

    abstract protected function getRootsQuery(): Builder;

    abstract protected function dataFromModel(Model $model): AbstractData;

    protected function getNodeTypes(): array
    {
        return [
            'default' => TreeNodeTypeData::make(
                key: 'default',
                label: 'Add '.$this->getEntityName(),
                formDataClass: $this->getDataClass(),
            )
                ->handler('handleFormSubmitted')
                ->modalTitle('Add '.$this->getEntityName())
                ->submitLabel('Save'),
        ];
    }

    protected function getCreateNodeTypeMap(): array
    {
        return $this->option('showAddButton', true)
            ? ['root' => ['default']]
            : [];
    }

    protected function getSortActions(): array
    {
        if ($this->manualSortingEnabled()) {
            return [];
        }

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

    protected function buildTreeItems(): array
    {
        $roots = $this->getRootsQuery()
            ->with(['children' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        $this->eagerLoadNestedChildren($roots);

        return $roots
            ->map(fn (Model $model) => $this->mapTreeNode($this->dataFromModel($model)))
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

        $children->load(['children' => fn ($q) => $q->orderBy('sort_order')]);
        $this->eagerLoadNestedChildren($children);
    }

    protected function mapTreeNode(AbstractData $data, int $depth = 0): TreeNodeData
    {
        $payload = $data->toArray();
        $children = collect($data->children ?? [])
            ->map(fn (mixed $child) => $this->mapTreeNode($child, $depth + 1))
            ->all();
        $id = isset($data->id) && is_numeric($data->id) ? (int) $data->id : null;

        return new TreeNodeData(
            key: $id ?? ($payload['key'] ?? $payload['name'] ?? uniqid('tree-node-', true)),
            name: (string) ($payload['name'] ?? $this->getEntityName()),
            nodeType: isset($payload['nodeType']) ? (string) $payload['nodeType'] : null,
            children: $children,
            formData: $payload,
            meta: ['sourceDataClass' => $data::class],
            depth: isset($data->depth) ? (int) $data->depth : $depth,
            id: $id,
        );
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

    public function canManuallySortItem(TreeNodeData $item): bool
    {
        return $this->manualSortingEnabled() && $item->id !== null;
    }

    public function reorderTreeGroup(string|int $groupKey, array $orderedKeys): void
    {
        if (! $this->manualSortingEnabled()) {
            Log::debug('cms.tree.reorder.skipped', [
                'component' => static::class,
                'reason' => 'manual_sorting_disabled',
                'groupKey' => $groupKey,
                'orderedKeys' => $orderedKeys,
            ]);
            return;
        }

        $normalizedKeys = collect($orderedKeys)
            ->filter(fn (mixed $key) => is_numeric($key))
            ->map(fn (mixed $key) => (int) $key)
            ->values();

        if ($normalizedKeys->isEmpty()) {
            Log::debug('cms.tree.reorder.skipped', [
                'component' => static::class,
                'reason' => 'empty_normalized_keys',
                'groupKey' => $groupKey,
                'orderedKeys' => $orderedKeys,
            ]);
            return;
        }

        $parentId = is_numeric($groupKey) ? (int) $groupKey : null;
        $siblings = $this->getBaseQuery()
            ->where('parent_id', $parentId)
            ->orderBy('sort_order')
            ->get();

        $siblingIds = $siblings->pluck('id')->map(fn (mixed $id) => (int) $id)->values();

        if ($siblingIds->sort()->values()->all() !== $normalizedKeys->sort()->values()->all()) {
            Log::debug('cms.tree.reorder.skipped', [
                'component' => static::class,
                'reason' => 'sibling_id_mismatch',
                'groupKey' => $groupKey,
                'orderedKeys' => $orderedKeys,
                'normalizedKeys' => $normalizedKeys->all(),
                'siblingIds' => $siblingIds->all(),
            ]);
            return;
        }

        $sortValues = $siblings->pluck('sort_order')->values()->all();
        $siblingsById = $siblings->keyBy('id');

        Log::debug('cms.tree.reorder.start', [
            'component' => static::class,
            'groupKey' => $groupKey,
            'normalizedKeys' => $normalizedKeys->all(),
            'sortValues' => $sortValues,
        ]);

        foreach ($normalizedKeys->values() as $index => $id) {
            $sibling = $siblingsById->get($id);

            if (! $sibling) {
                continue;
            }

            $newSort = $sortValues[$index] ?? $index;

            if ($sibling->sort_order !== $newSort) {
                $sibling->sort_order = $newSort;
                $sibling->save();
            }
        }

        $this->refreshTree();
        $this->emit();
        Log::debug('cms.tree.reorder.complete', [
            'component' => static::class,
            'groupKey' => $groupKey,
            'normalizedKeys' => $normalizedKeys->all(),
        ]);
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
        parent::mount();
        $this->mountEntityDefaults();

        if ($this->edit !== null) {
            $this->openEditFromUrl();
        }
    }
}
