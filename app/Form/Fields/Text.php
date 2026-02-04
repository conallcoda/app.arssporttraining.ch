<?php

namespace App\Form\Fields;

use App\Form\Concerns\HasMask;
use App\Form\Concerns\HasPlaceholder;
use App\Form\Concerns\HasSuffix;
use App\Form\Field;

class Text extends Field
{
    use HasMask;
    use HasPlaceholder;
    use HasSuffix;

    public string $type = 'text';
}
