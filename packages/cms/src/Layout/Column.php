<?php

namespace Coda\Cms\Layout;

use Coda\Cms\Layout\Concerns\HasSchema;

class Column
{
    use HasSchema;

    public int $span;

    public function __construct(int $span = 12)
    {
        $this->span = $span;
    }

    public static function make(int $span = 12): static
    {
        return new static($span);
    }
}
