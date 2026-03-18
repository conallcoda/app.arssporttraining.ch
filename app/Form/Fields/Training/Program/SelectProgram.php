<?php

namespace App\Form\Fields\Training\Program;

use App\Form\Fields\SelectEntity;
use App\Models\Exercise\ExerciseProgram;
use Closure;

class SelectProgram extends SelectEntity
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->placeholder = 'None';
        $this->required = false;
        $this->live = true;
        $this->variant = 'listbox';
        $this->validationRules = 'nullable|integer|exists:exercise_programs,id';
        $this->default = null;
    }

    public function withOptions(?Closure $constraint = null): static
    {
        $this->optionLoader = function () use ($constraint) {
            $query = ExerciseProgram::query()->orderBy('name');

            if ($constraint) {
                $constraint($query);
            }

            return $query->pluck('name', 'id')->all();
        };

        return $this;
    }
}
