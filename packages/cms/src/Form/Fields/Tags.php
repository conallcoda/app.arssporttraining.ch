<?php

namespace Coda\Cms\Form\Fields;

use App\Models\Tag;
use Coda\Cms\Form\Concerns\HasPlaceholder;
use Coda\Cms\Form\Field;

class Tags extends Field
{
    use HasPlaceholder;

    public string $type = 'tags';

    public string $scope;

    public array $options = [];

    protected ?\Closure $optionLoader = null;

    protected bool $optionsResolved = false;

    protected ?string $cacheKey = null;

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
        $scope = $this->scope;

        $this->optionLoader = function () use ($scope) {
            $tags = Tag::query()
                ->forScope($scope)
                ->whereNull('parent_id')
                ->orderBy('name')
                ->with(['children' => fn ($q) => $q->orderBy('name')])
                ->get();

            $options = [];

            foreach ($tags as $tag) {
                $options[$tag->id] = $tag->name;

                foreach ($tag->children as $child) {
                    $options[$child->id] = "{$tag->name} > {$child->name}";
                }
            }

            return $options;
        };

        return $this;
    }

    public function getOptions(): array
    {
        if (! $this->optionsResolved && $this->optionLoader) {
            if ($this->cacheKey !== null) {
                $cached = Field::getCachedOptions($this->cacheKey);

                if ($cached !== null) {
                    $this->options = $cached;
                    $this->optionsResolved = true;

                    return $this->options;
                }
            }

            $this->options = ($this->optionLoader)();
            $this->optionsResolved = true;

            if ($this->cacheKey !== null) {
                Field::setCachedOptions($this->cacheKey, $this->options);
            }
        }

        return $this->options;
    }

    public function cached(?string $key = null): static
    {
        $this->cacheKey = $key;

        return $this;
    }
}
