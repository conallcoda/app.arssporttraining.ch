<?php

namespace App\Cms\Form\Fields;

use App\Cms\Form\Concerns\HasSchema;
use App\Cms\Form\Field;

class Repeater extends Field
{
    use HasSchema;

    public string $type = 'repeater';
}
