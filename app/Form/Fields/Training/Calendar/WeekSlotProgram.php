<?php

namespace App\Form\Fields\Training\Calendar;

use App\Models\Training\TrainingProgram;
use Coda\Cms\Form\Fields\SelectEntity;

class WeekSlotProgram extends SelectEntity
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Program';
        $this->placeholder = 'Select a program';
        $this->required = true;
        $this->validationRules = 'required|integer|exists:training_programs,id';
        $this->default = null;
        $this->variant = 'listbox';
    }

    public function withOptions(?int $groupId = null, ?string $date = null): static
    {
        $this->optionLoader = function () use ($groupId) {
            if ($groupId === null) {
                return [];
            }

            return TrainingProgram::query()
                ->with('program')
                ->where('group_id', $groupId)
                ->orderBy('sort')
                ->get()
                ->pluck('program.name', 'id')
                ->all();
        };

        return $this;
    }
}
