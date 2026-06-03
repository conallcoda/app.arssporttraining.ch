<?php

namespace Coda\Cms\Layout;

use Coda\Cms\Layout\Concerns\HasSchema;

class Layout
{
    use HasSchema;

    public string $container = 'card';

    public static function make(): static
    {
        return new static;
    }

    public function container(string $container): static
    {
        $this->container = $container;

        return $this;
    }

    public function card(): static
    {
        return $this->container('card');
    }

    public function section(): static
    {
        return $this->container('section');
    }

    public function none(): static
    {
        return $this->container('none');
    }

    public function tabs(array $tabs, ?string $label = null, bool $scrollable = true): static
    {
        $this->schema[] = Tabs::make($tabs)
            ->label($label)
            ->scrollable($scrollable);

        return $this;
    }
}
