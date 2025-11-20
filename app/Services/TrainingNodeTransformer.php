<?php

namespace App\Services;

use App\Data\NavigationTree\TreeAction;
use App\Data\NavigationTree\TreeNode;
use App\Models\Training\TrainingNode as DomainTrainingNode;

class TrainingNodeTransformer
{
    private TreeOperations $treeOps;

    public function __construct()
    {
        $this->treeOps = new TreeOperations();
    }

    public function toNavigationTree(DomainTrainingNode $trainingNode): TreeNode
    {
        $children = [];
        foreach ($trainingNode->children as $child) {
            $children[] = $this->toNavigationTree($child);
        }

        $badge = null;
        if ($trainingNode->type === 'week' && count($trainingNode->children) > 0) {
            $badge = (string) count($trainingNode->children);
        }

        return new TreeNode(
            uuid: $trainingNode->uuid,
            label: $trainingNode->name(),
            icon: $this->getIconForType($trainingNode->type),
            badge: $badge,
            children: $children,
            metadata: [
                'type' => $trainingNode->type,
                'id' => $trainingNode->id,
                'sequence' => $trainingNode->sequence,
                'parent' => $trainingNode->parent,
            ],
        );
    }

    public function getActionsForNode(TreeNode $navigationTree, string $nodeUuid): array
    {
        $node = $this->treeOps->findNode($navigationTree, $nodeUuid);
        if ($node === null) {
            return [];
        }

        $position = $this->treeOps->getNodePosition($navigationTree, $nodeUuid);
        $type = $node->metadata['type'] ?? null;

        return match ($type) {
            'season' => $this->getSeasonActions(),
            'block' => $this->getBlockActions($position),
            'week' => $this->getWeekActions($position),
            default => [],
        };
    }

    private function getSeasonActions(): array
    {
        return [
            new TreeAction(
                name: 'add_block',
                label: 'Add Block',
                icon: 'list-plus',
            ),
        ];
    }

    private function getBlockActions(array $position): array
    {
        return [
            new TreeAction(
                name: 'add_week',
                label: 'Add Week',
                icon: 'list-plus',
            ),
            new TreeAction(
                name: 'duplicate',
                label: 'Duplicate',
                icon: 'copy-plus',
            ),
            new TreeAction(
                name: 'move_up',
                label: 'Move Up',
                icon: 'arrow-up',
                disabled: $position['isFirst'],
            ),
            new TreeAction(
                name: 'move_down',
                label: 'Move Down',
                icon: 'arrow-down',
                disabled: $position['isLast'],
            ),
            new TreeAction(
                name: 'delete',
                label: 'Delete',
                icon: 'trash',
                variant: 'danger',
                separator: true,
            ),
        ];
    }

    private function getWeekActions(array $position): array
    {
        return [
            new TreeAction(
                name: 'add_session',
                label: 'Add Session',
                icon: 'list-plus',
            ),
            new TreeAction(
                name: 'duplicate',
                label: 'Duplicate',
                icon: 'copy-plus',
            ),
            new TreeAction(
                name: 'move_up',
                label: 'Move Up',
                icon: 'arrow-up',
                disabled: $position['isFirst'],
            ),
            new TreeAction(
                name: 'move_down',
                label: 'Move Down',
                icon: 'arrow-down',
                disabled: $position['isLast'],
            ),
            new TreeAction(
                name: 'delete',
                label: 'Delete',
                icon: 'trash',
                variant: 'danger',
                separator: true,
            ),
        ];
    }

    private function getIconForType(string $type): ?string
    {
        return match ($type) {
            'season' => 'calendar',
            'block' => 'layers',
            'week' => 'calendar-days',
            'session' => 'clock',
            default => null,
        };
    }
}
