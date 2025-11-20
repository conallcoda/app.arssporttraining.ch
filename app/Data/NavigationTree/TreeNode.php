<?php

namespace App\Data\NavigationTree;

use App\Data\AbstractData;

class TreeNode extends AbstractData
{
    public function __construct(
        public string $uuid,
        public string $label,
        public ?string $icon = null,
        public ?string $badge = null,
        public array $children = [],
        public array $metadata = [],
    ) {}

    public function hasChildren(): bool
    {
        return !empty($this->children);
    }

    public function addChild(TreeNode $child): void
    {
        $this->children[] = $child;
    }
}
