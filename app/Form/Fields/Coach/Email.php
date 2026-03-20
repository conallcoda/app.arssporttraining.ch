<?php

namespace App\Form\Fields\Coach;

use Coda\Cms\Form\Fields\Text;
use Illuminate\Validation\Rule;

class Email extends Text
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Email';
        $this->placeholder = 'Email';
        $this->required = true;
        $this->default = '';
        $this->validationRules = fn (array $data) => [
            'required',
            'email',
            Rule::unique('users', 'email')->ignore($data['id'] ?? null),
        ];
    }
}
