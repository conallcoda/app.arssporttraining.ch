<?php

namespace Coda\Cms\Layout;

use Coda\Cms\Layout\Concerns\HasSchema;

class Grid
{
    use HasSchema;

    public int $columns;

    public function __construct(int $columns = 12)
    {
        $this->columns = $columns;
    }

    public static function make(int $columns = 12): static
    {
        return new static($columns);
    }
}
