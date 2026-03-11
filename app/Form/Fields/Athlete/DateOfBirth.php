<?php

namespace App\Form\Fields\Athlete;

use Coda\Cms\Form\Fields\Date;

class DateOfBirth extends Date
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Date of Birth';
        $this->required = false;
        $this->default = null;
        $this->validationRules = 'nullable|date';
    }
}
