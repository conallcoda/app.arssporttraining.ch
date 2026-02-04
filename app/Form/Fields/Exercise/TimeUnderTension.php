<?php

namespace App\Form\Fields\Exercise;

use App\Form\Fields\Text;

class TimeUnderTension extends Text
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Time Under Tension';
        $this->default = '3010';
    }
}
