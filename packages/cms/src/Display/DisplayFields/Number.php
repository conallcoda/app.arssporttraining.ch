<?php

namespace Coda\Cms\Display\DisplayFields;

use Coda\Cms\Display\Concerns\HasModal;
use Coda\Cms\Display\Concerns\HasPrefix;
use Coda\Cms\Display\Concerns\HasSuffix;
use Coda\Cms\Display\DisplayField;

class Number extends DisplayField
{
    use HasModal;
    use HasPrefix;
    use HasSuffix;

    public string $type = 'number';

    public function formatValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_int($value) || (is_numeric($value) && (float) $value === (float) (int) $value)) {
            return number_format((int) $value);
        }

        if (is_numeric($value)) {
            $formatted = number_format((float) $value, 2);

            return rtrim(rtrim($formatted, '0'), '.');
        }

        return (string) $value;
    }
}
