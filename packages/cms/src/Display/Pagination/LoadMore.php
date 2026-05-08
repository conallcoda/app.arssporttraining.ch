<?php

namespace Coda\Cms\Display\Pagination;

use Coda\Cms\Display\Pagination;

class LoadMore extends Pagination
{
    public function isAccumulating(): bool
    {
        return true;
    }

    public function partialName(): string
    {
        return 'load-more';
    }
}
