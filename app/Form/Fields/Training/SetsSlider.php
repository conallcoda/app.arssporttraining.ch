<?php

namespace App\Form\Fields\Training;

use App\Form\Fields\Slider;

class SetsSlider extends Slider
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Sets';
        $this->min = 1;
        $this->max = 6;
        $this->step = 1;
        $this->ticks = [1, 2, 3, 4, 5, 6];
        $this->suffix = 'sets';
        $this->default = 4;
    }
}
