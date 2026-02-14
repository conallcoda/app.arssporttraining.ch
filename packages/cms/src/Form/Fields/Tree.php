<?php

namespace Coda\Cms\Form\Fields;

use Coda\Cms\Form\Concerns\HasPlaceholder;
use Coda\Cms\Form\Field;

class Tree extends Field
{
    use HasPlaceholder;

    public string $type = 'tree';

    /** @var list<array{value: int, name: string, children: list<mixed>}> */
    public array $treeOptions = [];

    protected ?\Closure $optionLoader = null;

    protected bool $optionsResolved = false;

    protected ?string $cacheKey = null;

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

    /** @return list<array{value: int, name: string, children: list<mixed>}> */
    public function getTreeOptions(): array
    {
        if (! $this->optionsResolved && $this->optionLoader) {
            if ($this->cacheKey !== null) {
                $cached = Field::getCachedOptions($this->cacheKey);

                if ($cached !== null) {
                    $this->treeOptions = $cached;
                    $this->optionsResolved = true;

                    return $this->treeOptions;
                }
            }

            $this->treeOptions = ($this->optionLoader)();
            $this->optionsResolved = true;

            if ($this->cacheKey !== null) {
                Field::setCachedOptions($this->cacheKey, $this->treeOptions);
            }
        }

        return $this->treeOptions;
    }

    public function cached(?string $key = null): static
    {
        $this->cacheKey = $key;

        return $this;
    }

    /** @return array<int, string> */
    public function flatOptions(string $separator = ' / '): array
    {
        $result = [];
        $this->flattenBranch($this->getTreeOptions(), [], $separator, $result);

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
