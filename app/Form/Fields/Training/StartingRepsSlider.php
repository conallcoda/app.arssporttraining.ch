<?php

namespace App\Form\Fields\Training;

use App\Form\Fields\Slider;

class StartingRepsSlider extends Slider
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Starting Reps';
        $this->min = 1;
        $this->max = 25;
        $this->step = 1;
        $this->ticks = [5, 10, 15, 20, 25];
        $this->suffix = 'reps';
        $this->default = 10;
    }
}
