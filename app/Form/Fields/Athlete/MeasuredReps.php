<?php

namespace App\Form\Fields\Athlete;

use Coda\Cms\Form\Fields\Number;

class MeasuredReps extends Number
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Measured Reps';
        $this->min = 1;
        $this->step = 1;
        $this->suffix = 'reps';
        $this->default = 8;
    }
}
