<?php

namespace App\Form\Fields\Athlete;

use Coda\Cms\Form\Fields\Reps;

class MeasuredReps extends Reps
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Measured Reps';
        $this->default = 1;
    }
}
