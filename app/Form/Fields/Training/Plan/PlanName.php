<?php

namespace App\Form\Fields\Training\Plan;

use Coda\FormKit\Fields\Text;

class PlanName extends Text
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Name';
        $this->placeholder = 'Plan name';
        $this->required = true;
        $this->default = '';
        $this->validationRules = 'required|string|min:1';
    }
}
