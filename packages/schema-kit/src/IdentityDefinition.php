<?php

namespace Coda\SchemaKit;

use Closure;

class IdentityDefinition
{
    private Closure|string|null $title = null;

    private Closure|string|null $subtitle = null;

    private Closure|string|null $color = null;

    private Closure|string|null $icon = null;

    private Closure|string|IdentityImageDefinition|null $image = null;

    private Closure|string|null $href = null;

    private array $meta = [];

    public static function make(): static
    {
        return new static;
    }

    public function title(Closure|string|null $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function subtitle(Closure|string|null $subtitle): static
    {
        $this->subtitle = $subtitle;

        return $this;
    }

    public function color(Closure|string|null $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function icon(Closure|string|null $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function image(Closure|string|IdentityImageDefinition|null $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function href(Closure|string|null $href): static
    {
        $this->href = $href;

        return $this;
    }

    public function setMeta(string $key, mixed $value): static
    {
        $this->meta[$key] = $value;

        return $this;
    }

    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->meta[$key] ?? $default;
    }

    public function allMeta(): array
    {
        return $this->meta;
    }

    public function getTitle(): Closure|string|null
    {
        return $this->title;
    }

    public function getSubtitle(): Closure|string|null
    {
        return $this->subtitle;
    }

    public function getColor(): Closure|string|null
    {
        return $this->color;
    }

    public function getIcon(): Closure|string|null
    {
        return $this->icon;
    }

    public function getImage(): Closure|string|IdentityImageDefinition|null
    {
        return $this->image;
    }

    public function getImageDefinition(): ?IdentityImageDefinition
    {
        if ($this->image instanceof IdentityImageDefinition) {
            return $this->image;
        }

        if ($this->image === null) {
            return null;
        }

        return IdentityImage::make($this->image);
    }

    public function getHref(): Closure|string|null
    {
        return $this->href;
    }
}
