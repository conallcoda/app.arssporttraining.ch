<?php

namespace App\Livewire\Database;

use App\Data\Exercise\ExerciseTemplateData;
use App\Data\Exercise\Settings\PreviewSetting;
use App\Livewire\Concerns\InteractsWithPreview;
use Coda\Cms\Livewire\FormModal;
use Coda\FormKit\Form;
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
        bool $showDelete = false,
        array $contextData = [],
        array $excludeFields = [],
        array $formTypes = [],
        bool $persistOnSubmit = false,
        int $defaultWeeks = 1,
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
            contextData: $contextData,
            excludeFields: $excludeFields,
            formTypes: $formTypes,
            persistOnSubmit: $persistOnSubmit,
        );

        unset($this->fieldsets);
        $this->data = array_replace_recursive($this->buildDefaultsFromFieldsets(), $this->data);
        $this->applyPreviewDefaults();
        unset($this->fieldsets);
    }

    public function open(
        array $data = [],
        ?string $title = null,
        ?string $focusField = null,
        ?int $focusIndex = null,
        array $formTypes = [],
        ?string $activeFormType = null,
        array $formTypeData = [],
        ?string $actionName = null,
    ): void
    {
        parent::open($data, $title, $focusField, $focusIndex, $formTypes, $activeFormType, $formTypeData, $actionName);

        unset($this->fieldsets);
        $this->data = array_replace_recursive($this->buildDefaultsFromFieldsets(), $this->data);

        if (isset($data['config']['settings'])) {
            $this->data['config']['settings'] = $data['config']['settings'];
        }

        $this->openPreview($data);
        unset($this->fieldsets);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $preview
     * @return array{config: array<string, mixed>, preview: array<string, mixed>}
     */
    protected function applyPreviewGridContextOverrides(array $config, array $preview): array
    {
        $preview = array_merge($preview, [
            'weeks' => 1,
            'sessionsPerWeek' => 1,
            'groupingMode' => 'none',
            'groupSize' => 1,
            'copyValuesAutomatically' => false,
        ]);

        $config['preview'] = $preview;

        return [
            'config' => $config,
            'preview' => $preview,
        ];
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
