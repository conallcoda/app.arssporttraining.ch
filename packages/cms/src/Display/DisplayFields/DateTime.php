<?php

namespace Coda\Cms\Display\DisplayFields;

class DateTime extends Date
{
    public string $type = 'datetime';

    public function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return \Carbon\Carbon::parse($value)->format(
            $this->format ?? config('cms.display.datetime_format', 'd.m.Y H:i:s')
        );
    }
}
