<?php

namespace App\Form\Fields\Training\Plan;

use Coda\Cms\Form\Fields\Text;

class PlanName extends Text
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Name';
        $this->placeholder = 'Training plan name';
        $this->required = true;
        $this->default = '';
        $this->validationRules = 'required|string|min:1';
    }
}
