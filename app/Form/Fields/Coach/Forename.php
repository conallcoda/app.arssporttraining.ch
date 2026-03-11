<?php

namespace App\Form\Fields\Coach;

use Coda\Cms\Form\Fields\Text;

class Forename extends Text
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Forename';
        $this->placeholder = 'Forename';
        $this->required = true;
        $this->default = '';
        $this->validationRules = 'required|string|min:1';
    }
}
