<?php

namespace App\Form\Fields\Athlete;

use Coda\FormKit\Fields\Text;
use Illuminate\Validation\Rule;

class Email extends Text
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Email';
        $this->placeholder = 'Email';
        $this->required = false;
        $this->default = '';
        $this->validationRules = fn (array $data) => [
            'nullable',
            'email',
            Rule::unique('users', 'email')->ignore($data['id'] ?? null),
        ];
    }
}
