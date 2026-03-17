<?php

namespace Coda\Cms\Display\DisplayFields;

use Carbon\Carbon;
use Coda\Cms\Display\Concerns\HasModal;
use Coda\Cms\Display\Concerns\HasPrefix;
use Coda\Cms\Display\Concerns\HasSuffix;
use Coda\Cms\Display\DisplayField;

class Date extends DisplayField
{
    use HasModal;
    use HasPrefix;
    use HasSuffix;

    public string $type = 'date';

    public function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return Carbon::parse($value)->format('d.m.Y');
    }
}
