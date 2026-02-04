<?php

namespace App\Data\Form\Fields;

use App\Data\Form\Concerns\HasMask;
use App\Data\Form\Concerns\HasPlaceholder;
use App\Data\Form\Concerns\HasSuffix;
use App\Data\Form\Field;

class Text extends Field
{
    use HasMask;
    use HasPlaceholder;
    use HasSuffix;

    public string $type = 'text';
}
