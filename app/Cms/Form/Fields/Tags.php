<?php

namespace App\Cms\Form\Fields;

use App\Cms\Form\Concerns\HasPlaceholder;
use App\Cms\Form\Field;
use App\Models\Tag;

class Tags extends Field
{
    use HasPlaceholder;

    public string $type = 'tags';

    public string $scope;

    public array $options = [];

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

        return $this;
    }
}
