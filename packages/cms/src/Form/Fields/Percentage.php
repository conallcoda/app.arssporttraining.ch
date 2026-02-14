<?php

namespace Coda\Cms\Form\Fields;

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
