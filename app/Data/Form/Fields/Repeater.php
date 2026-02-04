<?php

namespace App\Data\Form\Fields;

use App\Data\Form\Concerns\HasSchema;
use App\Data\Form\Field;

class Repeater extends Field
{
    use HasSchema;

    public string $type = 'repeater';
}
