<?php

namespace Coda\Cms\Layout;

class Tabs
{
    public array $tabs = [];

    public ?string $label = null;

    public bool $scrollable = true;

    public static function make(array $tabs): static
    {
        $instance = new static;
        $instance->tabs = array_values($tabs);

        return $instance;
    }

    public function label(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function scrollable(bool $scrollable = true): static
    {
        $this->scrollable = $scrollable;

        return $this;
    }
}
