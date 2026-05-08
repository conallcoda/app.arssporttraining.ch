<?php

namespace Coda\Cms\Data;

class GroupedTreeItemData extends AbstractData
{
    public function __construct(
        public string $key,
        public string $nodeType,
        public string $name,
        public array $children = [],
        public array $formData = [],
        public array $meta = [],
        public array $ancestorKeys = [],
        public int $depth = 0,
        public bool $isFirstSibling = false,
        public bool $isLastSibling = false,
    ) {}
}
