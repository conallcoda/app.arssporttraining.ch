<?php

namespace App\Form\Fields\Exercise;

use App\Cms\Form\Fields\Number;

class Reps extends Number
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Reps';
        $this->default = 10;
        $this->min = 1;
        $this->suffix = 'rep(s)';
        $this->step = 1;
    }
}
