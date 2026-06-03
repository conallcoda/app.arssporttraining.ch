<?php

namespace Coda\Cms\Display\DisplayFields;

use Coda\Cms\Display\DisplayField;

class CompactDisplay extends DisplayField
{
    public string $type = 'compact-display';

    public ?\Closure $source = null;

    public function source(\Closure $callback): static
    {
        $this->source = $callback;

        return $this;
    }

    public function getSourceData(mixed $item): array
    {
        if ($this->source === null) {
            return [];
        }

        return ($this->source)($item);
    }
}
