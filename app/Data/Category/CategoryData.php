<?php

namespace App\Data\Category;

use App\Cms\Data\AbstractData;
use App\Cms\Form\Concerns\InteractsWithForms;
use App\Cms\Form\Fields\Category;
use App\Cms\Form\Fields\Text;
use App\Cms\Form\Form;
use App\Cms\Models\Contracts\HasForms;
use App\Models\Tag;
use Carbon\Carbon;

class CategoryData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public array $ancestorIds = [];

    public bool $isFirstSibling = false;

    public bool $isLastSibling = false;

    public function __construct(
        public ?int $id,
        public string $name,
        public ?int $parentId = null,
        public array $parents = [],
        public int $sortOrder = 0,
        public int $depth = 0,
        public array $children = [],
        public ?Carbon $updatedAt = null,
    ) {}

    public static function fromTag(Tag $tag): self
    {
        $parents = [];

        if ($tag->relationLoaded('ancestors')) {
            $parents = $tag->ancestors
                ->sortBy('depth')
                ->map(fn (Tag $ancestor) => [
                    'id' => $ancestor->id,
                    'name' => $ancestor->name,
                ])
                ->values()
                ->all();
        }

        return new self(
            id: $tag->id,
            name: $tag->name,
            parentId: $tag->parent_id,
            parents: $parents,
            updatedAt: $tag->updated_at,
        );
    }

    public static function fromTagTree(Tag $tag, int $depth = 0): self
    {
        $children = [];

        if ($tag->relationLoaded('children')) {
            $children = $tag->children
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->map(fn (Tag $child) => self::fromTagTree($child, $depth + 1))
                ->all();
        }

        return new self(
            id: $tag->id,
            name: $tag->name,
            parentId: $tag->parent_id,
            sortOrder: $tag->sort_order ?? 0,
            depth: $depth,
            children: $children,
            updatedAt: $tag->updated_at,
        );
    }

    public function persist(): void
    {
        $tag = Tag::updateOrCreate(
            ['id' => $this->id],
            [
                'name' => $this->name,
                'scope' => 'exercise_category',
                'parent_id' => $this->parentId,
                'sort_order' => $this->sortOrder,
            ]
        );

        $this->id = $tag->id;
    }

    public static function getForm(): Form
    {
        return Form::make()
            ->fieldset('General', [
                Text::make('name')->label('Name')->required(),
                Category::make('parentId', 'exercise_category')->label('Parent')->placeholder('None (top-level)')->withOptions(),
            ]);
    }
}
