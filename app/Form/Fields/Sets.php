<?php

namespace App\Form\Fields;

use Coda\Cms\Form\Fields\Number;

class Sets extends Number
{
    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->default = 5;
        $this->min = 1;
        $this->suffix = 'set(s)';
    }
}
