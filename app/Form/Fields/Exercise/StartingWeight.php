<?php

namespace App\Form\Fields\Exercise;

use App\Cms\Form\Fields\Weight;

class StartingWeight extends Weight
{
    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->label = 'Starting Weight';
    }
}
