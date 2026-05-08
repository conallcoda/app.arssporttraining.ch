<?php

namespace Coda\FormKit\Fields;

use Coda\FormKit\Concerns\HasPlaceholder;
use Coda\FormKit\Field;
use ReflectionFunction;

class Tree extends Field
{
    use HasPlaceholder;

    public string $type = 'tree';

    /** @var list<array{value: int, name: string, children: list<mixed>}> */
    public array $treeOptions = [];

    protected ?\Closure $optionLoader = null;

    protected bool $optionsResolved = false;

    protected ?string $cacheKey = null;

    protected bool $excludeRoot = false;

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->default = null;
    }

    public function options(array $treeOptions): static
    {
        $this->treeOptions = $treeOptions;
        $this->optionsResolved = true;

        return $this;
    }

    public function optionsUsing(\Closure $loader): static
    {
        $this->optionLoader = $loader;
        $this->optionsResolved = false;
        $this->treeOptions = [];

        return $this;
    }

    /** @return list<array{value: int, name: string, children: list<mixed>}> */
    public function getTreeOptions(array $context = []): array
    {
        if (! $this->optionsResolved && $this->optionLoader) {
            $cacheKey = $this->cacheKey;

            if ($cacheKey !== null && $context !== []) {
                $cacheKey .= ':'.md5(json_encode($context));
            }

            if ($cacheKey !== null) {
                $cached = Field::getCachedOptions($cacheKey);

                if ($cached !== null) {
                    $this->treeOptions = $cached;
                    $this->optionsResolved = true;

                    return $this->treeOptions;
                }
            }

            $reflection = new ReflectionFunction($this->optionLoader);

            $this->treeOptions = $reflection->getNumberOfParameters() > 0
                ? ($this->optionLoader)($context)
                : ($this->optionLoader)();
            $this->optionsResolved = true;

            if ($cacheKey !== null) {
                Field::setCachedOptions($cacheKey, $this->treeOptions);
            }
        }

        return $this->treeOptions;
    }

    public function cached(?string $key = null): static
    {
        $this->cacheKey = $key;

        return $this;
    }

    public function excludeRoot(bool $excludeRoot = true): static
    {
        $this->excludeRoot = $excludeRoot;

        return $this;
    }

    /** @return list<array{value: int, name: string, children: list<mixed>}> */
    public function getRenderableTreeOptions(array $context = []): array
    {
        $options = $this->getTreeOptions($context);

        if (! $this->excludeRoot || count($options) !== 1) {
            return $options;
        }

        return $options[0]['children'] ?? [];
    }

    /** @return array<int, string> */
    public function flatOptions(string $separator = ' / '): array
    {
        $result = [];
        $this->flattenBranch($this->getRenderableTreeOptions(), [], $separator, $result);

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
