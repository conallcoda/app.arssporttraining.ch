<?php

namespace App\Cms\Form\Fields;

use App\Cms\Form\Concerns\HasPlaceholder;
use App\Cms\Form\Field;

class Tree extends Field
{
    use HasPlaceholder;

    public string $type = 'tree';

    /** @var list<array{value: int, name: string, children: list<mixed>}> */
    public array $treeOptions = [];

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->default = null;
    }

    public function options(array $treeOptions): static
    {
        $this->treeOptions = $treeOptions;

        return $this;
    }

    /** @return array<int, string> */
    public function flatOptions(string $separator = ' / '): array
    {
        $result = [];
        $this->flattenBranch($this->treeOptions, [], $separator, $result);

        return $result;
    }

    /** @param list<array{value: int, name: string, children: list<mixed>}> $nodes */
    private function flattenBranch(array $nodes, array $path, string $separator, array &$result): void
    {
        foreach ($nodes as $node) {
            $currentPath = [...$path, $node['name']];
            $result[$node['value']] = implode($separator, $currentPath);

            if (! empty($node['children'])) {
                $this->flattenBranch($node['children'], $currentPath, $separator, $result);
            }
        }
    }
}
