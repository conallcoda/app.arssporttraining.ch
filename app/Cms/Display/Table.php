<?php

namespace App\Cms\Display;

class Table
{
    protected array $columns = [];

    protected array $actions = [];

    protected int $limit = 10;

    public static function make(): static
    {
        return new static;
    }

    public function columns(array $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    public function actions(array $actions): static
    {
        $this->actions = $actions;

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getActions(): array
    {
        return $this->actions;
    }

    public function hasActions(): bool
    {
        return count($this->actions) > 0;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }
}
