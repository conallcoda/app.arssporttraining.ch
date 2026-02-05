<?php

namespace App\Cms\Form\Fields;

use App\Cms\Form\Concerns\HasNumericConstraints;
use App\Cms\Form\Concerns\HasSuffix;
use App\Cms\Form\Concerns\HasTicks;
use App\Cms\Form\Field;

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
