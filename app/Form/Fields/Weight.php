<?php

namespace App\Form\Fields;

use Coda\FormKit\Fields\Number;

class Weight extends Number
{
    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->default = 0;
        $this->min = 0;
        $this->suffix = 'kg';
        $this->step = 0.5;
    }
}
