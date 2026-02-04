<?php

namespace App\Data\Form\Fields\General;

use App\Data\Form\Fields\Text;

class Tut extends Text
{
    public function __construct(string $name, bool $simple = false)
    {
        parent::__construct($name);

        if (! $simple) {
            $this->mask = '99-99-99-99';
            $this->placeholder = '3010';
            $this->validationRules = 'nullable|regex:/^\d{2}-\d{2}-\d{2}-\d{2}$/';
        }
    }

    public static function make(string $name, bool $simple = false): static
    {
        return new static($name, $simple);
    }
}
