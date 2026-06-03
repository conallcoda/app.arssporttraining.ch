<?php

namespace Coda\Cms\Display;

use Coda\Cms\Display\Pagination\Classic;
use Coda\Cms\Display\Pagination\Hybrid;
use Coda\Cms\Display\Pagination\Infinite;
use Coda\Cms\Display\Pagination\LoadMore;

abstract class Pagination
{
    protected int $perPage = 10;

    public static function classic(): Classic
    {
        return new Classic;
    }

    public static function loadMore(): LoadMore
    {
        return new LoadMore;
    }

    public static function infinite(): Infinite
    {
        return new Infinite;
    }

    public static function hybrid(): Hybrid
    {
        return new Hybrid;
    }

    public function perPage(int $perPage): static
    {
        $this->perPage = $perPage;

        return $this;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    abstract public function isAccumulating(): bool;

    abstract public function partialName(): string;
}
