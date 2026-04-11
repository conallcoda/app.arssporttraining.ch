<?php

namespace App\Form\Fields\Training\Program;

use Coda\FormKit\Fields\Text;

class ProgramName extends Text
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Name';
        $this->placeholder = 'Program name';
        $this->required = true;
        $this->default = '';
        $this->validationRules = 'required|string|min:1';
    }
}
