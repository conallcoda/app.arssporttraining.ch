<?php

namespace Coda\FormKit\Fields;

use Coda\FormKit\Concerns\HasLiveUpdates;
use Coda\FormKit\Concerns\HasCreateOption;
use Coda\FormKit\Concerns\HasOptions;
use Coda\FormKit\Concerns\HasPlaceholder;
use Coda\FormKit\Concerns\HasSelectVariants;
use Coda\FormKit\Concerns\HasUnique;
use Coda\FormKit\Field;

class Select extends Field
{
    use HasLiveUpdates;
    use HasCreateOption;
    use HasOptions;
    use HasPlaceholder;
    use HasSelectVariants;
    use HasUnique;

    public string $type = 'select';

    public bool $tree = false;

    /** @var list<array{value: int|string, name: string, children: list<mixed>}> */
    public array $treeOptions = [];

    protected ?\Closure $treeOptionLoader = null;

    protected bool $treeOptionsResolved = false;

    protected ?string $treeCacheKey = null;

    protected bool $treeExcludeRoot = false;

    public bool $treeLeafOnly = false;

    public function tree(bool $tree = true): static
    {
        $this->tree = $tree;

        return $this;
    }

    /** @param list<array{value: int|string, name: string, children: list<mixed>}> $treeOptions */
    public function treeOptions(array $treeOptions): static
    {
        $this->treeOptions = $treeOptions;
        $this->treeOptionsResolved = true;
        $this->tree = true;

        return $this;
    }

    public function treeOptionsUsing(\Closure $loader): static
    {
        $this->treeOptionLoader = $loader;
        $this->treeOptionsResolved = false;
        $this->treeOptions = [];
        $this->tree = true;

        return $this;
    }

    /** @return list<array{value: int|string, name: string, children: list<mixed>}> */
    public function getTreeOptions(array $context = []): array
    {
        if (! $this->treeOptionLoader) {
            return $this->treeOptions;
        }

        $cacheKey = $this->treeCacheKey;

        if ($cacheKey !== null && $context !== []) {
            $cacheKey .= ':'.md5(json_encode($context));
        }

        if ($cacheKey !== null) {
            $cached = Field::getCachedOptions($cacheKey);

            if ($cached !== null) {
                return $cached;
            }
        }

        $reflection = new \ReflectionFunction($this->treeOptionLoader);

        $options = $reflection->getNumberOfParameters() > 0
            ? ($this->treeOptionLoader)($context)
            : ($this->treeOptionLoader)();

        if ($cacheKey !== null) {
            Field::setCachedOptions($cacheKey, $options);
        }

        return $options;
    }

    public function treeCached(?string $key = null): static
    {
        $this->treeCacheKey = $key;

        return $this;
    }

    public function treeExcludeRoot(bool $excludeRoot = true): static
    {
        $this->treeExcludeRoot = $excludeRoot;

        return $this;
    }

    public function treeLeafOnly(bool $leafOnly = true): static
    {
        $this->treeLeafOnly = $leafOnly;

        return $this;
    }

    /** @return list<array{value: int|string, name: string, children: list<mixed>}> */
    public function getRenderableTreeOptions(array $context = []): array
    {
        $options = $this->getTreeOptions($context);

        if (! $this->treeExcludeRoot || count($options) !== 1) {
            return $options;
        }

        return $options[0]['children'] ?? [];
    }

    /** @return array<int|string, string> */
    public function flatTreeOptions(array $context = [], string $separator = ' / '): array
    {
        $result = [];
        $this->flattenTreeBranch($this->getRenderableTreeOptions($context), [], $separator, $result);

        return $result;
    }

    /** @param list<array{value: int|string, name: string, children: list<mixed>}> $nodes */
    private function flattenTreeBranch(array $nodes, array $path, string $separator, array &$result): void
    {
        foreach ($nodes as $node) {
            $currentPath = [...$path, $node['name']];
            $result[$node['value']] = implode($separator, $currentPath);

            if (! empty($node['children'])) {
                $this->flattenTreeBranch($node['children'], $currentPath, $separator, $result);
            }
        }
    }
}
