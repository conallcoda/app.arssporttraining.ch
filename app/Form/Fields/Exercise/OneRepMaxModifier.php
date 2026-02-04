<?php

namespace App\Form\Fields\Exercise;

use App\Form\Fields\Percentage;

class OneRepMaxModifier extends Percentage
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = '1RM (Modifier)';
        $this->default = 100;
    }
}
