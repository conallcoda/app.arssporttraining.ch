<?php

namespace App\Form\Fields\Exercise;

use App\Data\Exercise\ExerciseType as ExerciseTypeEnum;
use App\Form\Fields\Select;

class ExerciseType extends Select
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Type';
        $this->live = true;
        $this->enum(ExerciseTypeEnum::class);
    }
}
