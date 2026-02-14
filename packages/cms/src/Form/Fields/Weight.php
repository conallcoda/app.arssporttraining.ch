<?php

namespace Coda\Cms\Form\Fields;

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
