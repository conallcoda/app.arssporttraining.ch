<?php

namespace Coda\Cms\Layout;

use Coda\Cms\Layout\Concerns\HasSchema;

class Section
{
    use HasSchema;

    public ?string $label = null;

    public static function make(?string $label = null): static
    {
        $instance = new static;
        $instance->label = $label;

        return $instance;
    }
}
