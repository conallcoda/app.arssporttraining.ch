<?php

namespace App\Cms\Form\Fields;

use App\Cms\Form\Concerns\HasPlaceholder;
use App\Cms\Form\Field;
use App\Models\Tag;
use Illuminate\Support\Collection;

class Tags extends Field
{
    use HasPlaceholder;

    public string $type = 'tags';

    public string $scope;

    public array $options = [];

    public bool $useTree = false;

    /** @var list<array{value: int, name: string, children: list<mixed>}> */
    public array $treeOptions = [];

    public function __construct(string $name, string $scope)
    {
        parent::__construct($name);

        $this->scope = $scope;
        $this->default = [];
    }

    public static function make(string $name, ?string $scope = null): static
    {
        return new static($name, $scope ?? $name);
    }

    public function asTree(): static
    {
        $this->useTree = true;

        return $this;
    }

    public function withOptions(): static
    {
        $tags = Tag::query()
            ->forScope($this->scope)
            ->whereNull('parent_id')
            ->orderBy('name')
            ->with(['children' => fn ($q) => $q->orderBy('name')])
            ->get();

        $this->options = [];

        foreach ($tags as $tag) {
            $this->options[$tag->id] = $tag->name;

            foreach ($tag->children as $child) {
                $this->options[$child->id] = "{$tag->name} > {$child->name}";
            }
        }

        if ($this->useTree) {
            $allTags = Tag::query()
                ->forScope($this->scope)
                ->orderBy('name')
                ->get();

            $grouped = $allTags->groupBy('parent_id');
            $this->treeOptions = $this->buildBranch($grouped, null);
        }

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
