<?php

namespace App\Form\Fields\Athlete;

use Coda\Cms\Form\Fields\Number;

class AnaerobicThreshold extends Number
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Anaerobic Threshold';
        $this->required = true;
        $this->default = 90;
        $this->min = 1;
        $this->max = 100;
        $this->step = 1;
        $this->suffix = '%';
    }
}
