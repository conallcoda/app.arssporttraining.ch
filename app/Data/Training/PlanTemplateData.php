<?php

namespace App\Data\Training;

use App\Form\Fields\Training\Plan\PlanName;
use App\Models\PlanTemplate;
use Coda\Cms\Data\AbstractData;
use Coda\Cms\Form\Concerns\InteractsWithForms;
use Coda\Cms\Form\Form;
use Coda\Cms\Models\Contracts\HasForms;

class PlanTemplateData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public function __construct(
        public ?int $id,
        public string $name,
        public array $programs = [],
    ) {}

    public static function fromPlanTemplate(PlanTemplate $template): self
    {
        $programs = [];
        if ($template->relationLoaded('programs')) {
            $programs = $template->programs->map(fn ($program) => [
                'id' => $program->id,
                'name' => $program->name,
            ])->all();
        }

        return new self(
            id: $template->id,
            name: $template->name ?? '',
            programs: $programs,
        );
    }

    public function persist(): void
    {
        $template = PlanTemplate::updateOrCreate(
            ['id' => $this->id],
            ['name' => $this->name]
        );

        $this->id = $template->id;
    }

    public static function getForm(): Form
    {
        return Form::make()
            ->fieldset('General', [
                PlanName::make('name'),
            ]);
    }
}
