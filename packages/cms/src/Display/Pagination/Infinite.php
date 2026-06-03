<?php

namespace Coda\Cms\Display\Pagination;

use Coda\Cms\Display\Pagination;

class Infinite extends Pagination
{
    public function isAccumulating(): bool
    {
        return true;
    }

    public function partialName(): string
    {
        return 'infinite';
    }
}
