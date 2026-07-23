<?php

namespace Coda\Cms\Data;

class TreeNodeData extends AbstractData
{
    public function __construct(
        public string|int $key,
        public string $name,
        public ?string $nodeType = null,
        public array $children = [],
        public array $formData = [],
        public array $meta = [],
        public array $ancestorKeys = [],
        public array $ancestorIds = [],
        public int $depth = 0,
        public ?int $id = null,
        public bool $isFirstSibling = false,
        public bool $isLastSibling = false,
        public string|int|null $sortGroupKey = null,
    ) {}

    /**
     * @param  array<string, mixed>  $node
     */
    public static function fromArray(array $node, int $depth = 0): self
    {
        $children = self::collectionFromArray((array) ($node['children'] ?? []), $depth + 1);
        $key = $node['key'] ?? $node['id'] ?? ($node['name'] ?? uniqid('tree-node-', true));
        $id = isset($node['id']) && is_numeric($node['id']) ? (int) $node['id'] : (is_int($key) ? $key : null);

        return new self(
            key: $key,
            name: (string) ($node['name'] ?? $key),
            nodeType: isset($node['nodeType']) ? (string) $node['nodeType'] : null,
            children: $children,
            formData: (array) ($node['formData'] ?? $node),
            meta: (array) ($node['meta'] ?? []),
            depth: isset($node['depth']) ? (int) $node['depth'] : $depth,
            id: $id,
            sortGroupKey: $node['sortGroupKey'] ?? null,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, self>
     */
    public static function collectionFromArray(array $nodes, int $depth = 0): array
    {
        return collect($nodes)
            ->map(fn (array $node) => self::fromArray($node, $depth))
            ->all();
    }
}
