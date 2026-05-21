<?php

namespace Coda\Cms\Display\Pagination;

use Coda\Cms\Display\Pagination;

class Classic extends Pagination
{
    public function isAccumulating(): bool
    {
        return false;
    }

    public function partialName(): string
    {
        return 'classic';
    }
}
