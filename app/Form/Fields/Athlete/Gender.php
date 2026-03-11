<?php

namespace App\Form\Fields\Athlete;

use App\Models\Users\GenderEnum;
use Coda\Cms\Form\Fields\Select;

class Gender extends Select
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Gender';
        $this->placeholder = 'Select gender';
        $this->required = false;
        $this->default = null;
        $this->validationRules = 'nullable|integer|in:1,2';
        $this->options = collect(GenderEnum::cases())
            ->mapWithKeys(fn (GenderEnum $case) => [$case->value => $case->name])
            ->toArray();
    }
}
