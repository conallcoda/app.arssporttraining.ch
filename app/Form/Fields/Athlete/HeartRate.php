<?php

namespace App\Form\Fields\Athlete;

use Coda\Cms\Form\Fields\Number;

class HeartRate extends Number
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Heart Rate';
        $this->required = true;
        $this->min = 1;
        $this->max = 220;
        $this->step = 1;
        $this->suffix = 'bpm';
    }
}
