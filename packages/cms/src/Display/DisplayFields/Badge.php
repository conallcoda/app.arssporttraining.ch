<?php

namespace Coda\Cms\Display\DisplayFields;

use Coda\Cms\Display\Concerns\HasEnum;
use Coda\Cms\Display\Concerns\HasModal;
use Coda\Cms\Display\DisplayField;

class Badge extends DisplayField
{
    use HasEnum;
    use HasModal;

    public string $type = 'badge';

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
