<?php

namespace App\Form\Fields\Athlete;

use Coda\Cms\Form\Fields\Number;

class MaxHeartRate extends Number
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Max Heart Rate';
        $this->min = 1;
        $this->max = 220;
        $this->step = 1;
        $this->suffix = 'bpm';
    }
}
