<?php

namespace Coda\Cms\Layout;

use Illuminate\Support\Str;

class Fieldset
{
    public string $name;

    public function __construct(string $name)
    {
        $this->name = Str::snake($name);
    }

    public static function make(string $name): static
    {
        return new static($name);
    }
}
