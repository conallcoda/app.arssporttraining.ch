<?php

namespace App\Form\Fields\Coach;

use Coda\Cms\Form\Fields\Text;

class Email extends Text
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Email';
        $this->placeholder = 'Email';
        $this->required = false;
        $this->default = '';
        $this->validationRules = 'nullable|email';
    }
}
