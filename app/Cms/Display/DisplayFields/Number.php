<?php

namespace App\Cms\Display\DisplayFields;

use App\Cms\Display\Concerns\HasModal;
use App\Cms\Display\Concerns\HasPrefix;
use App\Cms\Display\Concerns\HasSuffix;
use App\Cms\Display\DisplayField;

class Number extends DisplayField
{
    use HasModal;
    use HasPrefix;
    use HasSuffix;

    public string $type = 'number';
}
