<?php

namespace App\Form\Fields\Exercise;

use App\Cms\Form\Fields\Text;

class Pace extends Text
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Pace';
        $this->default = '00:00';
        $this->suffix = 'min/km';
        $this->mask = '99:99';
    }
}
