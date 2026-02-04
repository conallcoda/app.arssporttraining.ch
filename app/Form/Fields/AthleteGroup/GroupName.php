<?php

namespace App\Form\Fields\AthleteGroup;

use App\Form\Fields\Text;

class GroupName extends Text
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Name';
        $this->placeholder = 'Group name';
        $this->required = true;
        $this->default = '';
        $this->validationRules = 'required|string|min:1';
    }
}
