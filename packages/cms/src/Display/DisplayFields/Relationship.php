<?php

namespace Coda\Cms\Display\DisplayFields;

use Coda\Cms\Display\Concerns\HasDisplayAttribute;
use Coda\Cms\Display\Concerns\HasModal;
use Coda\Cms\Display\DisplayField;

class Relationship extends DisplayField
{
    use HasDisplayAttribute;
    use HasModal;

    public string $type = 'relationship';
}
