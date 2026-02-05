<?php

namespace App\Form\Fields\Exercise;

use App\Cms\Form\Fields\Number;

class Duration extends Number
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Duration';
        $this->default = 0;
        $this->min = 0;
        $this->suffix = 's';
    }
}
