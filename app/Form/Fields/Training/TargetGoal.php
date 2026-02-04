<?php

namespace App\Form\Fields\Training;

use App\Form\Fields\Percentage;

class TargetGoal extends Percentage
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Target Goal';
        $this->default = 7;
    }
}
