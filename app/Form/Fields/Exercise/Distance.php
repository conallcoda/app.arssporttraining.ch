<?php

namespace App\Form\Fields\Exercise;

use App\Cms\Form\Fields\Number;

class Distance extends Number
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Distance';
        $this->default = 0;
        $this->min = 0;
        $this->suffix = 'm';
    }
}
