<?php

namespace App\Livewire\Database;

use App\Data\Exercise\ExerciseData;
use App\Data\Exercise\Settings\PreviewSetting;
use App\Livewire\Concerns\InteractsWithPreview;
use App\Models\Exercise\ExerciseTemplate;
use Coda\Cms\Form\Form;
use Coda\Cms\Livewire\FormModal;
use Illuminate\View\View;
use Livewire\Attributes\Computed;

class ExerciseForm extends FormModal
{
    use InteractsWithPreview;

    public array $preTemplateConfig = [];

    private ?string $previousTemplateId = null;

    public function mount(
        string $name = 'exercise-form',
        string $title = 'Exercise',
        ?string $formDataClass = null,
        string $submitLabel = 'Save',
        string $cancelLabel = 'Cancel',
        bool $flyout = true,
        string $maxWidth = 'max-w-[83.333%] overflow-x-hidden',
        bool $showDelete = false,
        int $defaultWeeks = 5,
        int $defaultSessionsPerWeek = 1,
        bool $showPreview = true,
        bool $showData = false,
    ): void {
        $this->initializePreview($defaultWeeks, $defaultSessionsPerWeek, $showPreview, $showData);

        parent::mount(
            name: $name,
            title: $title,
            formDataClass: $formDataClass,
            submitLabel: $submitLabel,
            cancelLabel: $cancelLabel,
            flyout: $flyout,
            maxWidth: $maxWidth,
            showDelete: $showDelete,
        );

        unset($this->fieldsets);
        $this->data = array_replace_recursive($this->buildDefaultsFromFieldsets(), $this->data);
        $this->applyPreviewDefaults();
        unset($this->fieldsets);
    }

    public function open(array $data = [], ?string $title = null, ?string $focusField = null, ?int $focusIndex = null): void
    {
        $this->preTemplateConfig = [];

        parent::open($data, $title, $focusField, $focusIndex);

        unset($this->fieldsets);
        $this->data = array_replace_recursive($this->buildDefaultsFromFieldsets(), $this->data);

        if (isset($data['config']['settings'])) {
            $this->data['config']['settings'] = $data['config']['settings'];
        }

        $this->openPreview($data);
        unset($this->fieldsets);
    }

    public function hydrate(): void
    {
        $this->previousTemplateId = $this->data['template'] ?? null;
    }

    public function updated(string $property, mixed $value): void
    {
        $templateChanged = false;

        if ($property === 'data.template') {
            $templateChanged = true;
        } elseif ($property === 'data') {
            $newTemplateId = $this->data['template'] ?? null;
            $templateChanged = $newTemplateId !== $this->previousTemplateId;
        }

        if ($templateChanged) {
            $this->applyTemplate($this->data['template'] ?? null);
        }

        parent::updated($property, $value);
    }

    protected function applyTemplate(mixed $templateId): void
    {
        if ($templateId) {
            $template = ExerciseTemplate::find($templateId);

            if ($template) {
                if (empty($this->preTemplateConfig)) {
                    $this->preTemplateConfig = $this->data['config'] ?? [];
                }

                $this->data['config'] = $template->config->toArray();
            }
        } else {
            if (! empty($this->preTemplateConfig)) {
                $this->data['config'] = $this->preTemplateConfig;
                $this->preTemplateConfig = [];
            }
        }

        unset($this->fieldsets);
        unset($this->previewGrid);

        $settings = $this->data['config']['settings'] ?? [];
        $this->data = array_replace_recursive($this->buildDefaultsFromFieldsets(), $this->data);
        $this->data['config']['settings'] = $settings;

        unset($this->fieldsets);
    }

    #[Computed]
    public function formConfig(): Form
    {
        $definition = ExerciseData::getForm();
        $form = $definition instanceof Form ? $definition : Form::fields($definition);

        $form->fieldset(
            'Preview',
            fn (array $data) => ['fields' => PreviewSetting::fields($data), 'prefix' => 'data.config.preview'],
        );
        $form->appendToFieldsetTabs('Settings', ['Preview']);

        return $form;
    }

    public function render(): View
    {
        return view('livewire.database.exercise-form');
    }
}
