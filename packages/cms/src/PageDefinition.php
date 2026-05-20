<?php

namespace Coda\Cms;

use Coda\Cms\Data\AbstractData;

class PageDefinition extends AbstractData
{
    public function __construct(
        public string $name = '',
        public string $route = '',
        public string $title = '',
        public string $heading = '',
        public string $layout = '',
        public ?string $component = null,
        /** @var string[] */
        public array $content = [],
        /** @var string[] */
        public array $middleware = [],
        public ?string $parent = null,
        /** @var string|\Closure|null */
        public mixed $breadcrumbLabel = null,
        /** @var array<string, class-string> */
        public array $breadcrumbBindings = [],
        public ?bool $breadcrumbs = null,
    ) {}

    public static function make(string $name): static
    {
        return new static(name: $name);
    }

    public function route(string $route): static
    {
        $this->route = $route;

        return $this;
    }

    public function title(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function heading(string $heading): static
    {
        $this->heading = $heading;

        return $this;
    }

    public function layout(string $layout): static
    {
        $this->layout = $layout;

        return $this;
    }

    public function component(string $component): static
    {
        $this->component = $component;

        return $this;
    }

    public function content(array $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function middleware(array $middleware): static
    {
        $this->middleware = $middleware;

        return $this;
    }

    public function parent(?string $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    public function breadcrumbLabel(string|\Closure|null $label): static
    {
        $this->breadcrumbLabel = $label;

        return $this;
    }

    /** @param array<string, class-string> $bindings */
    public function bindCrumb(string $param, string $modelClass): static
    {
        $this->breadcrumbBindings[$param] = $modelClass;

        return $this;
    }

    public function breadcrumbs(?bool $enabled): static
    {
        $this->breadcrumbs = $enabled;

        return $this;
    }

    public function isCustom(): bool
    {
        return $this->component !== null;
    }

    public function isGeneric(): bool
    {
        return $this->component === null;
    }
}
