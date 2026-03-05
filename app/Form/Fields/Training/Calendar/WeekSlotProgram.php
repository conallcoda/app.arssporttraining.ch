<?php

namespace App\Form\Fields\Training\Calendar;

use App\Models\Training\TrainingProgram;
use Coda\Cms\Form\Fields\Select;

class WeekSlotProgram extends Select
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

    public function withOptions(?int $groupId = null, ?int $userId = null, ?string $date = null): static
    {
        $this->optionLoader = function () use ($groupId, $userId) {
            if ($groupId === null) {
                return [];
            }

            $query = TrainingProgram::query()
                ->with('program')
                ->where('group_id', $groupId);

            if ($userId !== null) {
                $query->where(function ($q) use ($userId) {
                    $q->whereNull('user_id')->orWhere('user_id', $userId);
                });
            } else {
                $query->whereNull('user_id');
            }

            return $query
                ->orderBy('sort')
                ->get()
                ->pluck('program.name', 'id')
                ->all();
        };

        return $this;
    }
}
