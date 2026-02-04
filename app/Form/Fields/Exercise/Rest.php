<?php

namespace App\Form\Fields\Exercise;

use App\Form\Fields\Number;

class Rest extends Number
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Rest Between Sets';
        $this->default = 30;
        $this->min = 0;
        $this->suffix = 's';
        $this->step = 5;
    }
}
