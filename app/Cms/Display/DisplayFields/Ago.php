<?php

namespace App\Cms\Display\DisplayFields;

use App\Cms\Display\Concerns\HasPrefix;
use App\Cms\Display\Concerns\HasSuffix;
use App\Cms\Display\DisplayField;
use Carbon\Carbon;

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
