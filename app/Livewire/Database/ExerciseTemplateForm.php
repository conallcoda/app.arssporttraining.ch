<?php

namespace App\Livewire\Database;

use App\Data\Exercise\ExerciseTemplateData;
use App\Data\Exercise\Settings\PreviewSetting;
use App\Livewire\Concerns\InteractsWithPreview;
use Coda\Cms\Form\Form;
use Coda\Cms\Livewire\FormModal;
use Illuminate\View\View;
use Livewire\Attributes\Computed;

class ExerciseTemplateForm extends FormModal
{
    use InteractsWithPreview;

    public function mount(
        string $name = 'exercise-template-form',
        string $title = 'Exercise Template',
        ?string $formDataClass = null,
        string $submitLabel = 'Save',
        string $cancelLabel = 'Cancel',
        bool $flyout = true,
        string $maxWidth = 'max-w-[83.333%] overflow-x-hidden',
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
        );

        unset($this->fieldsets);
        $this->data = array_replace_recursive($this->buildDefaultsFromFieldsets(), $this->data);
        $this->applyPreviewDefaults();
        unset($this->fieldsets);
    }

    public function open(array $data = [], ?string $title = null, ?string $focusField = null, ?int $focusIndex = null): void
    {
        parent::open($data, $title, $focusField, $focusIndex);

        unset($this->fieldsets);
        $this->data = array_replace_recursive($this->buildDefaultsFromFieldsets(), $this->data);

        if (isset($data['config']['settings'])) {
            $this->data['config']['settings'] = $data['config']['settings'];
        }

        $this->openPreview($data);
        unset($this->fieldsets);
    }

    #[Computed]
    public function formConfig(): Form
    {
        $definition = ExerciseTemplateData::getForm();
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
