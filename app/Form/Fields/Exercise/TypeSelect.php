<?php

namespace App\Form\Fields\Exercise;

use App\Data\Exercise\ExerciseType;
use App\Form\Fields\Select;

class TypeSelect extends Select
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Type';
        $this->live = true;
        $this->enum(ExerciseType::class);
    }
}
