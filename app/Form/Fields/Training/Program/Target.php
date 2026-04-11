<?php

namespace App\Form\Fields\Training\Program;

use Coda\FormKit\Fields\Slider;

class Target extends Slider
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Target (%)';
        $this->min = 1;
        $this->max = 15;
        $this->step = 0.5;
        $this->ticks = [2.5, 5, 7.5, 10, 12.5, 15];
        $this->suffix = '%';
        $this->default = 7;
    }
}
