<?php

namespace Coda\FormKit\Fields;

use Coda\FormKit\Concerns\HasSchema;
use Coda\FormKit\Field;

class Repeater extends Field
{
    use HasSchema;

    public string $type = 'repeater';
}
