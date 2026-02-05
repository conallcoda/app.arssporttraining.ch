<?php

namespace App\Cms\Form\Fields;

use App\Cms\Form\Concerns\HasMask;
use App\Cms\Form\Concerns\HasPlaceholder;
use App\Cms\Form\Concerns\HasSuffix;
use App\Cms\Form\Field;

class Text extends Field
{
    use HasMask;
    use HasPlaceholder;
    use HasSuffix;

    public string $type = 'text';
}
