<?php

namespace App\Form\Fields;

use App\Form\Concerns\HasNumericConstraints;
use App\Form\Concerns\HasSuffix;
use App\Form\Concerns\HasTicks;
use App\Form\Field;

class Slider extends Field
{
    use HasNumericConstraints;
    use HasSuffix;
    use HasTicks;

    public string $type = 'slider';

    public function __construct(string $name)
    {
        parent::__construct($name);
    }
}
