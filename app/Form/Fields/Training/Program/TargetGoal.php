<?php

namespace App\Form\Fields\Training\Program;

use Coda\FormKit\Fields\Percentage;

class TargetGoal extends Percentage
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Target Goal';
        $this->default = 10;
    }
}
