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
        public string $layout = 'components.layouts.database',
        public ?string $component = null,
        /** @var string[] */
        public array $content = [],
        /** @var string[] */
        public array $middleware = [],
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

    public function isCustom(): bool
    {
        return $this->component !== null;
    }

    public function isGeneric(): bool
    {
        return $this->component === null;
    }
}
