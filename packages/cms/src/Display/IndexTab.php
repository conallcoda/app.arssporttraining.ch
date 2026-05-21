<?php

namespace Coda\Cms\Display;

use Closure;

class IndexTab
{
    public ?Closure $queryCallback = null;

    public ?string $icon = null;

    public function __construct(
        public string $key,
        public string $label,
    ) {}

    public static function make(string $key, string $label): self
    {
        return new self($key, $label);
    }

    public function query(Closure $callback): self
    {
        $this->queryCallback = $callback;

        return $this;
    }

    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function applyQuery(mixed $query): mixed
    {
        if ($this->queryCallback) {
            ($this->queryCallback)($query);
        }

        return $query;
    }
}
