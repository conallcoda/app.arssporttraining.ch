<?php

namespace App\Form\Fields;

use App\Form\Concerns\HasSchema;
use App\Form\Field;

class Repeater extends Field
{
    use HasSchema;

    public string $type = 'repeater';
}
