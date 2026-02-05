<?php

namespace App\Form\Fields\Exercise;

use App\Cms\Form\Fields\Number;

class StartingReps extends Number
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Starting Reps';
        $this->default = 12;
        $this->min = 1;
        $this->suffix = 'rep(s)';
        $this->step = 1;
    }
}
