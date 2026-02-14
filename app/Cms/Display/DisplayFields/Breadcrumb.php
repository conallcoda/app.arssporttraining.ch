<?php

namespace App\Cms\Display\DisplayFields;

use App\Cms\Display\DisplayField;

class Breadcrumb extends DisplayField
{
    public string $type = 'breadcrumb';

    public ?\Closure $source = null;

    public function source(\Closure $callback): static
    {
        $this->source = $callback;

        return $this;
    }

    /** @return list<string> */
    public function getSegments(mixed $item): array
    {
        if ($this->source === null) {
            return [];
        }

        return ($this->source)($item);
    }
}
