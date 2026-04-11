<?php

namespace App\Form\Fields\Coach;

use Coda\FormKit\Fields\Text;

class Phone extends Text
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Phone';
        $this->placeholder = 'Phone';
        $this->required = false;
        $this->default = '';
        $this->validationRules = 'nullable|string';
    }
}
