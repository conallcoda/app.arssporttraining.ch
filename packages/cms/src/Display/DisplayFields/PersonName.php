<?php

namespace Coda\Cms\Display\DisplayFields;

use Coda\Cms\Display\Concerns\HasModal;
use Coda\Cms\Display\Concerns\HasPrefix;
use Coda\Cms\Display\Concerns\HasSuffix;
use Coda\Cms\Display\DisplayField;

class PersonName extends DisplayField
{
    use HasModal;
    use HasPrefix;
    use HasSuffix;

    public string $type = 'text';
}
