<?php

namespace App\Form\Fields\Training\Plan;

use Coda\Cms\Form\Fields\Number;

class Duration extends Number
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Duration';
        $this->min = 1;
        $this->step = 1;
        $this->suffix = 'weeks';
        $this->default = 5;
    }
}
