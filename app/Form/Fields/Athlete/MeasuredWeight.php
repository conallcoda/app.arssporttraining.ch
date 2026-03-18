<?php

namespace App\Form\Fields\Athlete;

use Coda\Cms\Form\Fields\Number;

class MeasuredWeight extends Number
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Measured Weight';
        $this->required = true;
        $this->min = 0;
        $this->step = 0.5;
        $this->suffix = 'kg';
        $this->default = 50;
    }
}
