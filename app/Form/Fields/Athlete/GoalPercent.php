<?php

namespace App\Form\Fields\Athlete;

use Coda\Cms\Form\Fields\Number;

class GoalPercent extends Number
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Goal %';
        $this->default = 10;
        $this->min = 1;
        $this->max = 100;
        $this->step = 1;
        $this->suffix = '%';
    }
}
