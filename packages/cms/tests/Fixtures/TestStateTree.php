<?php

namespace Coda\Cms\Tests\Fixtures;

use Coda\Cms\Data\TreeNodeData;
use Coda\Cms\Livewire\AbstractTree;

class TestStateTree extends AbstractTree
{
    public array $treeState = [];

    public function mount(): void
    {
        parent::mount();

        $this->treeState = [
            [
                'key' => 'alpha',
                'name' => 'Alpha',
                'children' => [
                    [
                        'key' => 'alpha-1',
                        'name' => 'Alpha Child',
                    ],
                ],
            ],
            [
                'key' => 'beta',
                'name' => 'Beta',
            ],
        ];
    }

    protected function getEntityName(): string
    {
        return 'State Node';
    }

    protected function buildTreeItems(): array
    {
        return TreeNodeData::collectionFromArray($this->treeState);
    }
}
