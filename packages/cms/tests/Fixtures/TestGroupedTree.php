<?php

namespace Coda\Cms\Tests\Fixtures;

use Coda\Cms\Data\GroupedTreeItemData;
use Coda\Cms\Livewire\AbstractGroupedModelTree;
use Coda\FormKit\Action;

class TestGroupedTree extends AbstractGroupedModelTree
{
    protected function getEntityName(): string
    {
        return 'Grouped Item';
    }

    protected function getActions(): array
    {
        return [
            Action::make('addLeaf', 'Add Leaf')
                ->header()
                ->formModal(CmsTestLeafData::class, 'Add Leaf')
                ->handler('handleLeafSubmitted'),
            Action::make('editGroup', 'Edit')
                ->row()
                ->formModal(CmsTestItemData::class, 'Edit Group')
                ->passesItemData()
                ->handler('handleGroupSubmitted')
                ->visibleWhen(fn (GroupedTreeItemData $item) => $item->nodeType === 'group'),
            Action::make('editLeaf', 'Edit')
                ->row()
                ->formModal(CmsTestLeafData::class, 'Edit Leaf')
                ->passesItemData()
                ->handler('handleLeafSubmitted')
                ->visibleWhen(fn (GroupedTreeItemData $item) => $item->nodeType === 'leaf'),
            Action::make('addLeafToGroup', 'Add Leaf')
                ->rowMenu()
                ->formModal(CmsTestLeafData::class, 'Add Leaf')
                ->handler('handleLeafSubmitted')
                ->prepareData(fn (GroupedTreeItemData $item, array $data) => array_replace($data, [
                    'groupId' => $item->meta['groupId'] ?? null,
                ]))
                ->visibleWhen(fn (GroupedTreeItemData $item) => $item->nodeType === 'group'),
        ];
    }

    protected function buildTreeItems(): array
    {
        return CmsTestItem::query()
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (CmsTestItem $group) => $this->groupItem($group))
            ->all();
    }

    protected function groupItem(CmsTestItem $group, int $depth = 0): GroupedTreeItemData
    {
        $childGroups = $group->children
            ->sortBy('sort_order')
            ->map(fn (CmsTestItem $child) => $this->groupItem($child, $depth + 1))
            ->values()
            ->all();

        $leafItems = CmsTestLeaf::query()
            ->where('group_id', $group->id)
            ->orderBy('name')
            ->get()
            ->map(fn (CmsTestLeaf $leaf) => new GroupedTreeItemData(
                key: 'leaf:'.$leaf->id,
                nodeType: 'leaf',
                name: $leaf->name,
                formData: CmsTestLeafData::fromModel($leaf)->toArray(),
                meta: ['leafId' => $leaf->id, 'groupId' => $group->id],
                depth: $depth + 1,
            ))
            ->all();

        return new GroupedTreeItemData(
            key: 'group:'.$group->id,
            nodeType: 'group',
            name: $group->name,
            children: [...$childGroups, ...$leafItems],
            formData: CmsTestItemData::fromModel($group, $depth)->toArray(),
            meta: ['groupId' => $group->id],
            depth: $depth,
        );
    }

    public function handleGroupSubmitted(array $data): void
    {
        $dto = CmsTestItemData::from($data);
        $dto->persist();
        $this->refreshTree();
    }

    public function handleLeafSubmitted(array $data): void
    {
        $dto = CmsTestLeafData::from($data);
        $dto->persist();
        $this->refreshTree();
    }
}
