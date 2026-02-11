<?php

namespace App\Cms\Display\DisplayFields;

use App\Cms\Display\Concerns\HasEnum;
use App\Cms\Display\Concerns\HasModal;
use App\Cms\Display\DisplayField;

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
