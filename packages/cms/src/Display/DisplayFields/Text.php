<?php

namespace Coda\Cms\Display\DisplayFields;

use Coda\Cms\Display\Concerns\HasEnum;
use Coda\Cms\Display\Concerns\HasModal;
use Coda\Cms\Display\Concerns\HasPrefix;
use Coda\Cms\Display\Concerns\HasSuffix;
use Coda\Cms\Display\DisplayField;

class Text extends DisplayField
{
    use HasEnum;
    use HasModal;
    use HasPrefix;
    use HasSuffix;

    public string $type = 'text';
}
