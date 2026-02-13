<?php

namespace App\Cms\Form\Fields;

class Sets extends Number
{
    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->default = 25;
        $this->min = 1;
        $this->suffix = 'set(s)';
    }
}
