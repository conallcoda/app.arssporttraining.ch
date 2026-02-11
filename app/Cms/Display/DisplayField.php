<?php

namespace App\Cms\Display;

use App\Cms\Display\Concerns\HasLabel;
use App\Cms\Display\Concerns\HasSortable;
use App\Cms\Display\Concerns\HasSticky;
use App\Cms\Display\Concerns\HasWidth;

abstract class DisplayField
{
    use HasLabel;
    use HasSortable;
    use HasSticky;
    use HasWidth;

    public string $field;

    public string $type;

    public function __construct(string $field)
    {
        $this->field = $field;
    }

    public static function make(string $field): static
    {
        return new static($field);
    }

    public function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return (string) $value;
    }
}
