<?php

namespace Coda\Cms\Layout;

use Coda\Cms\Layout\Concerns\HasSchema;
use Illuminate\Support\Str;

class Tab
{
    use HasSchema;

    public string $name;

    public string $label;

    public function __construct(string $label)
    {
        $this->label = $label;
        $this->name = Str::snake($label);
    }

    public static function make(string $label): static
    {
        return new static($label);
    }

    public function name(string $name): static
    {
        $this->name = Str::snake($name);

        return $this;
    }
}
