<?php

namespace App\Cms\Display\DisplayFields;

use App\Cms\Display\Concerns\HasDisplayAttribute;
use App\Cms\Display\Concerns\HasModal;
use App\Cms\Display\DisplayField;

class Relationship extends DisplayField
{
    use HasDisplayAttribute;
    use HasModal;

    public string $type = 'relationship';
}
