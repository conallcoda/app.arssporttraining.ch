<?php

namespace Coda\Cms\Display;

use Coda\Cms\Display\Concerns\HasLabel;
use Coda\Cms\Display\Concerns\HasSortable;
use Coda\Cms\Display\Concerns\HasSticky;
use Coda\Cms\Display\Concerns\HasWidth;

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
