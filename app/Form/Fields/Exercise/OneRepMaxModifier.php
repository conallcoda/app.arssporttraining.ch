<?php

namespace App\Form\Fields\Exercise;

use App\Cms\Form\Fields\Percentage;

class OneRepMaxModifier extends Percentage
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = '1RM (Modifier)';
        $this->default = 100;
    }
}
