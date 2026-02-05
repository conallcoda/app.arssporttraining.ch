<?php

namespace App\Form\Fields\Exercise;

use App\Cms\Form\Fields\Select;
use App\Data\Exercise\ExerciseType as ExerciseTypeEnum;

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
