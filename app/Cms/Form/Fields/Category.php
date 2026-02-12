<?php

namespace App\Cms\Form\Fields;

use App\Models\Tag;
use Illuminate\Support\Collection;

class Category extends Tree
{
    public string $scope;

    public function __construct(string $name, string $scope)
    {
        parent::__construct($name);

        $this->scope = $scope;
    }

    public static function make(string $name, ?string $scope = null): static
    {
        return new static($name, $scope ?? $name);
    }

    public function withOptions(): static
    {
        $allTags = Tag::query()
            ->forScope($this->scope)
            ->orderBy('name')
            ->get();

        $grouped = $allTags->groupBy('parent_id');
        $this->treeOptions = $this->buildBranch($grouped, null);

        return $this;
    }

    /** @return list<array{value: int, name: string, children: list<mixed>}> */
    private function buildBranch(Collection $grouped, ?int $parentId): array
    {
        return $grouped->get($parentId, collect())
            ->map(fn (Tag $tag) => [
                'value' => $tag->id,
                'name' => $tag->name,
                'children' => $this->buildBranch($grouped, $tag->id),
            ])->values()->toArray();
    }
}
