<?php

namespace App\Form\Fields\Athlete;

use Coda\Cms\Form\Fields\Text;

class DateOfBirth extends Text
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Date of Birth';
        $this->required = false;
        $this->default = null;
        $this->placeholder = 'dd.mm.yyyy';
        $this->mask = '99.99.9999';
        $this->validationRules = 'nullable|date_format:d.m.Y';
    }
}
