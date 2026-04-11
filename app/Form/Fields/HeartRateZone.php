<?php

namespace App\Form\Fields;

use Coda\FormKit\Fields\Text;

class HeartRateZone extends Text
{
    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->suffix = 'zone';
    }
}
