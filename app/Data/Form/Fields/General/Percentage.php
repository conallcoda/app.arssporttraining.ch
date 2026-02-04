<?php

namespace App\Data\Form\Fields\General;

use App\Data\Form\Fields\Number;

class Percentage extends Number
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->min = 0;
        $this->max = 999;
        $this->step = 1;
        $this->suffix = '%';
    }
}
