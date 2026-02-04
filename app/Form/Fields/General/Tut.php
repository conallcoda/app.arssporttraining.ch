<?php

namespace App\Form\Fields\General;

use App\Form\Fields\Text;

class Tut extends Text
{
    public function __construct(string $name)
    {
        parent::__construct($name);
    }

    public static function make(string $name): static
    {
        return new static($name, $simple);
    }
}
