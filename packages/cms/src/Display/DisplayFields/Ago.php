<?php

namespace Coda\Cms\Display\DisplayFields;

use Carbon\Carbon;
use Coda\Cms\Display\Concerns\HasPrefix;
use Coda\Cms\Display\Concerns\HasSuffix;
use Coda\Cms\Display\DisplayField;

class Ago extends DisplayField
{
    use HasPrefix;
    use HasSuffix;

    public string $type = 'ago';

    public function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return Carbon::parse($value)->diffForHumans();
    }
}
