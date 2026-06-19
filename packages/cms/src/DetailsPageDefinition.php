<?php

namespace Coda\Cms;

use Coda\Cms\Livewire\ModelDetailsPage;

class DetailsPageDefinition extends PageDefinition
{
    public function __construct(
        string $name = '',
        string $route = '',
        string $title = '',
        string $heading = '',
        string $layout = '',
        ?string $component = null,
        array $content = [],
        array $middleware = [],
        public ?string $listComponent = null,
    ) {
        parent::__construct(
            name: $name,
            route: $route,
            title: $title,
            heading: $heading,
            layout: $layout,
            component: $component ?? ModelDetailsPage::class,
            content: $content,
            middleware: $middleware,
        );
    }

    public static function make(string $name): static
    {
        return new static(name: $name);
    }

    public function listComponent(string $listComponent): static
    {
        $this->listComponent = $listComponent;

        return $this;
    }
}
