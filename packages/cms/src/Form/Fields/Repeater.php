<?php

namespace Coda\Cms\Form\Fields;

use Coda\Cms\Form\Concerns\HasSchema;
use Coda\Cms\Form\Field;

class Repeater extends Field
{
    use HasSchema;

    public string $type = 'repeater';
}
