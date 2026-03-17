<?php

namespace App\Form\Fields\Training\Program;

use App\Models\Exercise\ExerciseProgram;
use Coda\Cms\Form\Fields\Select;

class SelectProgram extends Select
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->placeholder = 'None';
        $this->required = false;
        $this->validationRules = 'nullable|integer|exists:exercise_programs,id';
        $this->default = null;
    }

    public function withOptions(): static
    {
        $this->optionLoader = fn () => ExerciseProgram::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        return $this;
    }
}
