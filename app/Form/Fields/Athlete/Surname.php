<?php

namespace App\Form\Fields\Athlete;

use Coda\FormKit\Fields\Text;

class Surname extends Text
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Surname';
        $this->placeholder = 'Surname';
        $this->required = true;
        $this->default = '';
        $this->validationRules = 'required|string|min:1';
    }
}
