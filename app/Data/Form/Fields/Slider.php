<?php

namespace App\Data\Form\Fields;

use App\Data\Form\Concerns\HasNumericConstraints;
use App\Data\Form\Concerns\HasSuffix;
use App\Data\Form\Concerns\HasTicks;
use App\Data\Form\Field;

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
