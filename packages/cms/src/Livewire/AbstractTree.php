<?php

namespace Coda\Cms\Livewire;

use Coda\Cms\Data\TreeColumnData;
use Coda\Cms\Data\TreeNodeTypeData;
use Coda\Cms\Data\TreeNodeData;
use Coda\Cms\Display\CardDefinition;
use Coda\FormKit\Action;
use Coda\FormKit\ActionPlacement;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

abstract class AbstractTree extends Component
{
    protected int $defaultExpand = 0;

    protected bool $showExpandCollapseControls = true;

    public array $options = [];

    public string|int|null $selectedRootKey = null;

    public string|int|null $confirmingKey = null;

    public ?string $confirmingAction = null;

    public ?string $confirmDescription = null;

    public int $refreshKey = 0;

    abstract protected function buildTreeItems(): array;

    protected function getActions(): array
    {
        return [];
    }

    /**
     * @return array<string, TreeNodeTypeData>
     */
    protected function getNodeTypes(): array
    {
        return [];
    }

    /**
     * @return array<int, TreeColumnData>
     */
    protected function getTreeColumns(): array
    {
        return [
            TreeColumnData::make('name', 'Name')
                ->hierarchy()
                ->widthClass('w-full'),
        ];
    }

    /**
     * @return array<int|string, array<int, string>>
     */
    protected function getCreateNodeTypeMap(): array
    {
        return [];
    }

    protected function getMaxDepth(): ?int
    {
        return null;
    }

    protected function usesTypedCreateModal(): bool
    {
        return false;
    }

    protected function usesManualSorting(): bool
    {
        return false;
    }

    protected function childDisplayMode(): string
    {
        return 'rows';
    }

    protected function leafCardDefinition(): ?CardDefinition
    {
        return null;
    }

    protected function leafCardWidth(): int
    {
        return 280;
    }

    protected function leafCardMinWidth(): string
    {
        return $this->leafCardWidth().'px';
    }

    protected function leafCardItemClass(): ?string
    {
        return null;
    }

    protected function leafCardBodyClass(): string
    {
        return 'p-4 flex-1 flex flex-col gap-3';
    }

    protected function leafCardFooterClass(): string
    {
        return 'mt-auto pt-2 flex items-center justify-between gap-2 border-t border-zinc-200 dark:border-zinc-700 -mx-4 -mb-4 px-4 py-2';
    }

    protected function leafCardParentLabelClass(): string
    {
        return '';
    }

    protected function leafCardParentCellClass(): string
    {
        return '';
    }

    protected function leafCardParentRowClass(): string
    {
        return '';
    }

    public function usesRootFilter(): bool
    {
        return false;
    }

    public function rootFilterVariant(): string
    {
        return 'segmented';
    }

    protected function hideSelectedRootRow(): bool
    {
        return false;
    }

    public function showSelectedRootHeading(): bool
    {
        return false;
    }

    public function filteredEmptyHeading(): string
    {
        return 'No items yet';
    }

    protected function getDefaultOptions(): array
    {
        return [];
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

    public function mount(): void
    {
        $this->options = array_merge($this->getDefaultOptions(), $this->options);
        $this->ensureSelectedRootKey();
    }

    public function render(): View
    {
        return view('cms::tree', [
            'entityName' => $this->getEntityName(),
            'entitySlug' => $this->getEntitySlug(),
            'options' => $this->options,
        ]);
    }

    #[Computed(persist: false)]
    public function treeItems(): array
    {
        return $this->buildTreeItems();
    }

    #[Computed(persist: false)]
    public function flatTreeItems(): array
    {
        return $this->flattenTree($this->treeItems, []);
    }

    #[Computed(persist: false)]
    public function displayTreeItems(): array
    {
        if (! $this->usesRootFilter()) {
            return $this->treeItems;
        }

        $selectedRoot = $this->selectedRootItem();

        if ($selectedRoot === null) {
            return [];
        }

        if ($this->hideSelectedRootRow()) {
            return $selectedRoot->children ?? [];
        }

        return [$selectedRoot];
    }

    #[Computed(persist: false)]
    public function displayFlatTreeItems(): array
    {
        return $this->flattenTree($this->displayTreeItems, []);
    }

    #[Computed]
    public function treeColumns(): array
    {
        $columns = $this->getTreeColumns();

        if ($columns === []) {
            $columns = [
                TreeColumnData::make('name', 'Name')
                    ->hierarchy()
                    ->widthClass('w-full'),
            ];
        }

        if (! collect($columns)->contains(fn (TreeColumnData $column) => $column->hierarchy)) {
            $columns[0]->hierarchy = true;
        }

        return $columns;
    }

    #[Computed]
    public function hierarchyColumn(): TreeColumnData
    {
        return collect($this->treeColumns)
            ->first(fn (TreeColumnData $column) => $column->hierarchy)
            ?? $this->treeColumns[0];
    }

    #[Computed]
    public function secondaryTreeColumns(): array
    {
        return collect($this->treeColumns)
            ->reject(fn (TreeColumnData $column) => $column->hierarchy)
            ->values()
            ->all();
    }

    public function treeColumnCount(): int
    {
        return count($this->treeColumns) + 1;
    }

    public function canManuallySortItem(TreeNodeData $item): bool
    {
        return false;
    }

    public function reorderTreeGroup(string|int $groupKey, array $orderedKeys): void
    {
        // Implement in concrete trees that opt into manual sorting.
    }

    public function usesChildCards(): bool
    {
        return $this->childDisplayMode() === 'cards' && $this->leafCardDefinition() !== null;
    }

    public function childCardDefinition(): ?CardDefinition
    {
        return $this->leafCardDefinition();
    }

    public function childCardWidth(): int
    {
        return $this->leafCardWidth();
    }

    public function childCardMinWidth(): string
    {
        return $this->leafCardMinWidth();
    }

    public function childCardItemClass(): ?string
    {
        return $this->leafCardItemClass();
    }

    public function childCardBodyClass(): string
    {
        return $this->leafCardBodyClass();
    }

    public function childCardFooterClass(): string
    {
        return $this->leafCardFooterClass();
    }

    public function childCardParentLabelClass(): string
    {
        return $this->leafCardParentLabelClass();
    }

    public function childCardParentCellClass(): string
    {
        return $this->leafCardParentCellClass();
    }

    public function childCardParentRowClass(): string
    {
        return $this->leafCardParentRowClass();
    }

    public function defaultExpandDepth(): int
    {
        return max(0, $this->defaultExpand);
    }

    public function showTreeExpandCollapseControls(): bool
    {
        return $this->showExpandCollapseControls;
    }

    public function shouldRenderChildCards(TreeNodeData $item): bool
    {
        if (! $this->usesChildCards()) {
            return false;
        }

        if (($maxDepth = $this->getMaxDepth()) !== null && $maxDepth !== 1) {
            return false;
        }

        if (empty($item->children)) {
            return false;
        }

        return collect($item->children)->every(fn (TreeNodeData $child) => empty($child->children));
    }

    /**
     * @param  array<int, TreeNodeData>  $items
     * @param  array<int, string|int>  $ancestorKeys
     * @return array<int, TreeNodeData>
     */
    protected function flattenTree(array $items, array $ancestorKeys): array
    {
        $result = [];
        $count = count($items);
        $sortGroupKey = $ancestorKeys === [] ? 'root' : end($ancestorKeys);

        foreach ($items as $index => $item) {
            $itemKey = $item->key ?? $item->id ?? null;

            if ($itemKey === null) {
                throw new \RuntimeException('Tree items must define either a key or id property.');
            }

            $item->ancestorKeys = $ancestorKeys;
            $item->ancestorIds = collect($ancestorKeys)
                ->filter(fn (string|int $key) => is_int($key) || (is_string($key) && ctype_digit($key)))
                ->map(fn (string|int $key) => (int) $key)
                ->values()
                ->all();
            $item->isFirstSibling = ($index === 0);
            $item->isLastSibling = ($index === $count - 1);
            $item->sortGroupKey = $sortGroupKey;
            $result[] = $item;

            if (! empty($item->children)) {
                $childAncestorKeys = array_merge($ancestorKeys, [$itemKey]);
                $result = array_merge($result, $this->flattenTree($item->children, $childAncestorKeys));
            }
        }

        return $result;
    }

    protected function refreshTree(): void
    {
        unset($this->treeItems, $this->flatTreeItems);
        $this->refreshKey++;
    }

    public function manualSortingEnabled(): bool
    {
        return $this->usesManualSorting();
    }

    public function createModalName(): string
    {
        return 'create-'.$this->getEntitySlug();
    }

    protected function ensureSelectedRootKey(): void
    {
        if (! $this->usesRootFilter()) {
            return;
        }

        $availableRootKey = $this->rootFilterItems[0]['key'] ?? null;

        if ($availableRootKey === null) {
            $this->selectedRootKey = null;

            return;
        }

        if ($this->selectedRootKey === null || ! collect($this->rootFilterItems)->contains(fn (array $item) => (string) $item['key'] === (string) $this->selectedRootKey)) {
            $this->selectedRootKey = $availableRootKey;
        }
    }

    #[Computed]
    public function rootFilterItems(): array
    {
        return collect($this->treeItems)
            ->map(function (mixed $item): array {
                $key = $item->key ?? $item->id ?? null;

                return [
                    'key' => $key,
                    'name' => $item->name ?? (string) $key,
                ];
            })
            ->filter(fn (array $item) => $item['key'] !== null)
            ->values()
            ->all();
    }

    public function selectedRootItem(): ?TreeNodeData
    {
        $this->ensureSelectedRootKey();

        return collect($this->treeItems)
            ->first(fn (mixed $item) => (string) ($item->key ?? $item->id ?? '') === (string) $this->selectedRootKey);
    }

    public function selectedRootHeading(): ?string
    {
        return $this->selectedRootItem()?->name;
    }

    #[Computed(persist: false)]
    public function expandableTreeKeys(): array
    {
        return $this->collectExpandableTreeKeys($this->displayTreeItems);
    }

    #[Computed(persist: false)]
    public function defaultExpandedTreeKeys(): array
    {
        if ($this->defaultExpandDepth() === 0) {
            return [];
        }

        return $this->collectDefaultExpandedTreeKeys($this->displayTreeItems);
    }

    public function shouldRenderTreeExpandCollapseControls(): bool
    {
        return $this->showTreeExpandCollapseControls() && $this->expandableTreeKeys !== [];
    }

    /**
     * @return array<int, TreeNodeTypeData>
     */
    protected function rootNodeTypes(): array
    {
        return $this->resolveNodeTypesForDepth(0, null);
    }

    /**
     * @return array<int, TreeNodeTypeData>
     */
    protected function childNodeTypes(TreeNodeData $parent): array
    {
        $targetDepth = $parent->depth + 1;

        if (($maxDepth = $this->getMaxDepth()) !== null && $targetDepth > $maxDepth) {
            return [];
        }

        return $this->resolveNodeTypesForDepth($targetDepth, $parent);
    }

    protected function resolveNodeType(string $key): ?TreeNodeTypeData
    {
        return $this->getNodeTypes()[$key] ?? null;
    }

    /**
     * @return array<int, TreeNodeTypeData>
     */
    protected function resolveNodeTypesForDepth(int $depth, ?TreeNodeData $parent): array
    {
        $map = $this->getCreateNodeTypeMap();
        $keys = $map[$depth]
            ?? ($depth === 0 ? ($map['root'] ?? null) : null)
            ?? ($map['*'] ?? []);

        return collect((array) $keys)
            ->map(fn (string $key) => $this->resolveNodeType($key))
            ->filter(fn (?TreeNodeTypeData $type) => $type !== null && $type->isVisible($parent))
            ->values()
            ->all();
    }

    public function canCreateRoots(): bool
    {
        if (! $this->usesTypedCreateModal()) {
            return false;
        }

        return count($this->headerNodeTypes()) > 0;
    }

    public function canCreateChildrenForItem(TreeNodeData $item): bool
    {
        if (! $this->usesTypedCreateModal()) {
            return false;
        }

        return count($this->childNodeTypes($item)) > 0;
    }

    public function createButtonLabel(): string
    {
        $types = $this->headerNodeTypes();

        if (count($types) === 1) {
            return $types[0]->label;
        }

        return 'Add '.$this->getEntityName();
    }

    public function createButtonIcon(): string
    {
        $types = $this->headerNodeTypes();

        if (count($types) === 1) {
            return $types[0]->icon ?? 'plus';
        }

        return 'plus';
    }

    public function openCreateModal(string|int|null $parentKey = null): void
    {
        $parent = $parentKey !== null
            ? $this->findItem($parentKey)
            : $this->headerCreateParent();
        $types = $parent ? $this->childNodeTypes($parent) : $this->rootNodeTypes();

        if (empty($types)) {
            return;
        }

        $formTypes = [];
        $formTypeData = [];

        foreach ($types as $type) {
            $seedData = [];

            if ($type->prepareData) {
                $seedData = (array) ($type->prepareData)($parent, $seedData, $this);
            }

            $seedData['_treeNodeType'] = $type->key;

            $formTypes[] = [
                'key' => $type->key,
                'label' => $type->label,
                'icon' => $type->icon,
                'formDataClass' => $type->formDataClass,
                'submitLabel' => $type->submitLabel,
            ];

            $formTypeData[$type->key] = $seedData;
        }

        $activeType = $formTypes[0]['key'] ?? null;
        $activeNodeType = $activeType ? $this->resolveNodeType($activeType) : null;

        $this->dispatch(
            'open-'.$this->createModalName(),
            data: $activeType ? ($formTypeData[$activeType] ?? []) : [],
            title: $activeNodeType?->modalTitle ?? $this->createButtonLabel(),
            formTypes: $formTypes,
            activeFormType: $activeType,
            formTypeData: $formTypeData,
        );
    }

    public function handleTreeCreateSubmitted(array $data): void
    {
        $typeKey = $data['_treeNodeType'] ?? null;

        if (! is_string($typeKey) || $typeKey === '') {
            return;
        }

        $type = $this->resolveNodeType($typeKey);

        if ($type === null) {
            return;
        }

        $handler = $type->resolvedHandler();
        $this->$handler($data);
    }

    /**
     * @return array<int, TreeNodeTypeData>
     */
    protected function headerNodeTypes(): array
    {
        $parent = $this->headerCreateParent();

        return $parent ? $this->childNodeTypes($parent) : $this->rootNodeTypes();
    }

    protected function headerCreateParent(): ?TreeNodeData
    {
        if (! $this->usesRootFilter()) {
            return null;
        }

        if (count($this->rootNodeTypes()) > 0) {
            return null;
        }

        return $this->selectedRootItem();
    }

    protected function findItem(string|int $key): ?TreeNodeData
    {
        foreach ($this->flatTreeItems as $item) {
            if ((string) $item->key === (string) $key) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param  array<int, TreeNodeData>  $items
     * @return array<int, string>
     */
    protected function collectExpandableTreeKeys(array $items): array
    {
        $keys = [];

        foreach ($items as $item) {
            if (empty($item->children)) {
                continue;
            }

            $keys[] = (string) $this->resolveTreeItemKey($item);
            $keys = array_merge($keys, $this->collectExpandableTreeKeys($item->children));
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param  array<int, TreeNodeData>  $items
     * @return array<int, string>
     */
    protected function collectDefaultExpandedTreeKeys(array $items, int $depth = 0): array
    {
        $keys = [];

        foreach ($items as $item) {
            if (empty($item->children)) {
                continue;
            }

            $itemKey = (string) $this->resolveTreeItemKey($item);

            if ($depth < $this->defaultExpandDepth()) {
                $keys[] = $itemKey;
            }

            $keys = array_merge($keys, $this->collectDefaultExpandedTreeKeys($item->children, $depth + 1));
        }

        return array_values(array_unique($keys));
    }

    protected function resolveTreeItemKey(TreeNodeData $item): string|int
    {
        $itemKey = $item->key ?? $item->id ?? null;

        if ($itemKey === null) {
            throw new \RuntimeException('Tree items must define either a key or id property.');
        }

        return $itemKey;
    }

    #[Computed]
    public function actions(): array
    {
        return $this->getActions();
    }

    #[Computed]
    public function headerActions(): array
    {
        return collect($this->actions)
            ->filter(fn (Action $action) => $action->placement === ActionPlacement::Header)
            ->values()
            ->all();
    }

    #[Computed]
    public function rowActions(): array
    {
        return collect($this->actions)
            ->filter(fn (Action $action) => $action->placement === ActionPlacement::Row)
            ->values()
            ->all();
    }

    #[Computed]
    public function rowMenuActions(): array
    {
        return collect($this->actions)
            ->filter(fn (Action $action) => $action->placement === ActionPlacement::RowMenu)
            ->values()
            ->all();
    }

    #[Computed]
    public function formModals(): array
    {
        return [];
    }

    #[Computed]
    public function confirmModals(): array
    {
        return [];
    }
}
