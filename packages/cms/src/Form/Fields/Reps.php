<?php

namespace Coda\Cms\Form\Fields;

class Reps extends Number
{
    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->min = 1;
        $this->step = 1;
        $this->suffix = 'rep(s)';
    }
}
