<?php

namespace Coda\Cms\Display\DisplayFields;

use Coda\Cms\Display\DisplayField;

class ColorBadge extends DisplayField
{
    public string $type = 'color-badge';

    public array $colorLabels = [];

    public function colorLabels(array $colorLabels): static
    {
        $this->colorLabels = $colorLabels;

        return $this;
    }

    public function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return $this->colorLabels[$value] ?? ucfirst((string) $value);
    }
}
