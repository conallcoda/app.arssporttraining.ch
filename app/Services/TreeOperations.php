<?php

namespace App\Services;

use App\Data\NavigationTree\TreeNode;

class TreeOperations
{
    public function findNode(TreeNode $tree, string $uuid): ?TreeNode
    {
        if ($tree->uuid === $uuid) {
            return $tree;
        }

        foreach ($tree->children as $child) {
            $found = $this->findNode($child, $uuid);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    public function findParentNode(TreeNode $tree, string $childUuid): ?TreeNode
    {
        foreach ($tree->children as $child) {
            if ($child->uuid === $childUuid) {
                return $tree;
            }

            $found = $this->findParentNode($child, $childUuid);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    public function addChild(TreeNode $parent, TreeNode $child): void
    {
        $parent->addChild($child);
    }

    public function removeNode(TreeNode $tree, string $uuid): bool
    {
        foreach ($tree->children as $index => $child) {
            if ($child->uuid === $uuid) {
                array_splice($tree->children, $index, 1);
                return true;
            }

            if ($this->removeNode($child, $uuid)) {
                return true;
            }
        }

        return false;
    }

    public function duplicateNode(TreeNode $tree, string $uuid): ?TreeNode
    {
        $parent = $this->findParentNode($tree, $uuid);
        if ($parent === null) {
            return null;
        }

        $nodeIndex = null;
        foreach ($parent->children as $index => $child) {
            if ($child->uuid === $uuid) {
                $nodeIndex = $index;
                break;
            }
        }

        if ($nodeIndex === null) {
            return null;
        }

        $originalNode = $parent->children[$nodeIndex];
        $duplicatedNode = $this->deepCloneNode($originalNode);

        array_splice($parent->children, $nodeIndex + 1, 0, [$duplicatedNode]);

        return $duplicatedNode;
    }

    public function moveNode(TreeNode $tree, string $uuid, string $direction): bool
    {
        $parent = $this->findParentNode($tree, $uuid);
        if ($parent === null) {
            return false;
        }

        $nodeIndex = null;
        foreach ($parent->children as $index => $child) {
            if ($child->uuid === $uuid) {
                $nodeIndex = $index;
                break;
            }
        }

        if ($nodeIndex === null) {
            return false;
        }

        if ($direction === 'up' && $nodeIndex > 0) {
            $temp = $parent->children[$nodeIndex];
            $parent->children[$nodeIndex] = $parent->children[$nodeIndex - 1];
            $parent->children[$nodeIndex - 1] = $temp;
            return true;
        }

        if ($direction === 'down' && $nodeIndex < count($parent->children) - 1) {
            $temp = $parent->children[$nodeIndex];
            $parent->children[$nodeIndex] = $parent->children[$nodeIndex + 1];
            $parent->children[$nodeIndex + 1] = $temp;
            return true;
        }

        return false;
    }

    public function buildFlatList(TreeNode $tree, array &$flatList = []): array
    {
        $flatList[$tree->uuid] = $tree;

        foreach ($tree->children as $child) {
            $this->buildFlatList($child, $flatList);
        }

        return $flatList;
    }

    public function getNodePosition(TreeNode $tree, string $uuid): array
    {
        $parent = $this->findParentNode($tree, $uuid);
        if ($parent === null) {
            return ['isFirst' => true, 'isLast' => true, 'index' => 0, 'total' => 1];
        }

        foreach ($parent->children as $index => $child) {
            if ($child->uuid === $uuid) {
                return [
                    'isFirst' => $index === 0,
                    'isLast' => $index === count($parent->children) - 1,
                    'index' => $index,
                    'total' => count($parent->children),
                ];
            }
        }

        return ['isFirst' => false, 'isLast' => false, 'index' => -1, 'total' => 0];
    }

    private function deepCloneNode(TreeNode $node): TreeNode
    {
        $clonedChildren = [];
        foreach ($node->children as $child) {
            $clonedChildren[] = $this->deepCloneNode($child);
        }

        return new TreeNode(
            uuid: \Illuminate\Support\Str::uuid()->toString(),
            label: $node->label,
            icon: $node->icon,
            badge: $node->badge,
            children: $clonedChildren,
            metadata: $node->metadata,
        );
    }
}
