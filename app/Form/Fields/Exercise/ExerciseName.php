<?php

namespace App\Form\Fields\Exercise;

use App\Cms\Form\Fields\Text;

class ExerciseName extends Text
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Name';
        $this->placeholder = 'Name';
        $this->required = true;
    }
}
