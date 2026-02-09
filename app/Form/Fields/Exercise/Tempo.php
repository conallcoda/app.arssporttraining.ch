<?php

namespace App\Form\Fields\Exercise;

use App\Cms\Form\Fields\Text;

class Tempo extends Text
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Tempo';
        $this->default = '3010';
    }
}
