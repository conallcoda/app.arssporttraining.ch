<?php

namespace App\Data\Training;

use App\Form\Fields\Training\Plan\PlanName;
use App\Models\ExercisePlan;
use Coda\Cms\Data\AbstractData;
use Coda\Cms\Form\Concerns\InteractsWithForms;
use Coda\Cms\Form\Form;
use Coda\Cms\Models\Contracts\HasForms;

class ExercisePlanData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public function __construct(
        public ?int $id,
        public string $name,
    ) {}

    public static function fromExercisePlan(ExercisePlan $exercisePlan): self
    {
        return new self(
            id: $exercisePlan->id,
            name: $exercisePlan->name ?? '',
        );
    }

    public function persist(): void
    {
        $exercisePlan = ExercisePlan::updateOrCreate(
            ['id' => $this->id],
            ['name' => $this->name]
        );

        $this->id = $exercisePlan->id;
    }

    public static function getForm(): Form
    {
        return Form::make()
            ->fieldset('General', [
                PlanName::make('name'),
            ]);
    }
}
