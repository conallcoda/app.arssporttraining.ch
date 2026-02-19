<?php

namespace App\Data\Exercise;

use App\Models\Exercise\ExerciseTemplate;
use Carbon\Carbon;
use Coda\Cms\Data\AbstractData;
use Coda\Cms\Form\Concerns\InteractsWithForms;
use Coda\Cms\Form\Fields;
use Coda\Cms\Form\Form;
use Coda\Cms\Models\Contracts\HasForms;

class ExerciseTemplateData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public function __construct(
        public ?int $id,
        public string $name,
        public ExerciseConfig $config = new ExerciseConfig,
        public ?Carbon $updatedAt = null,
    ) {}

    public static function fromTemplate(ExerciseTemplate $template): self
    {
        return new self(
            id: $template->id,
            name: $template->name,
            config: $template->config ?? new ExerciseConfig,
            updatedAt: $template->updated_at,
        );
    }

    public function persist(): void
    {
        $template = ExerciseTemplate::updateOrCreate(
            ['id' => $this->id],
            [
                'name' => $this->name,
                'config' => $this->config?->toArray() ?? [],
            ]
        );

        $this->id = $template->id;
    }

    public static function getForm(): Form
    {
        $form = Form::make()
            ->fieldset('General', [
                Fields\Text::make('name')->live(),
            ]);

        ExerciseConfig::addFormFieldsets($form);

        $form->fieldsetTabs(['General', 'Settings']);

        return $form;
    }

    public function getDefaultsBadges(): array
    {
        if (empty($this->config->settings)) {
            return [];
        }

        return collect($this->config->settings)
            ->map(fn (string $setting) => ['label' => ucfirst($setting), 'modalField' => $setting])
            ->values()
            ->all();
    }
}
