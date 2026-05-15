<?php

namespace App\Livewire\Training\View;

use App\Data\Exercise\ExerciseConfig;
use App\Data\Exercise\Preview\OverrideManager;
use App\Data\Exercise\Preview\SessionGroupingConfig;
use App\Data\Exercise\Preview\SessionGroupingMode;
use App\Support\Training\ApplyPerScope;
use Coda\Cms\Livewire\FormModal;
use Coda\FormKit\Form;
use Coda\FormKit\FormFieldsetGroup;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

class PlanExerciseSettingsForm extends FormModal
{
    public ?int $contextExerciseId = null;

    public ?int $contextProgramExerciseId = null;

    public ?int $contextUserId = null;

    public array $contextSessionGrouping = [];

    public function mount(
        string $name = 'plan-exercise-settings',
        string $title = 'exercise-settings-default',
        ?string $formDataClass = null,
        string $submitLabel = 'save-default',
        string $cancelLabel = 'cancel-default',
        bool $flyout = true,
        string $maxWidth = 'max-w-lg',
        bool $showDelete = false,
        array $contextData = [],
        array $excludeFields = [],
        array $formTypes = [],
        bool $persistOnSubmit = false,
    ): void {
        parent::mount(
            name: $name,
            title: $title === 'exercise-settings-default' ? __('Exercise Settings') : $title,
            formDataClass: $formDataClass,
            submitLabel: $submitLabel === 'save-default' ? __('Save') : $submitLabel,
            cancelLabel: $cancelLabel === 'cancel-default' ? __('Cancel') : $cancelLabel,
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
        unset($this->fieldsets);
    }

    #[Computed]
    public function formConfig(): Form
    {
        $form = Form::make();
        ExerciseConfig::addFormFieldsets($form, [
            [
                'label' => 'Grouping',
                'fields' => SessionGroupingConfig::fields($this->data['session_grouping'] ?? []),
                'prefix' => 'data.session_grouping',
            ],
        ]);

        return $form;
    }

    #[Computed]
    public function fieldsets(): array
    {
        $fieldsets = $this->formConfig->resolveFieldsets($this->data);

        foreach ($fieldsets as $item) {
            if ($item instanceof FormFieldsetGroup) {
                $item->label = null;
            }
        }

        return $fieldsets;
    }

    public function getListeners(): array
    {
        return [];
    }

    #[On('open-plan-exercise-settings')]
    public function openForExercise(array $data): void
    {
        $this->contextExerciseId = $data['exerciseId'] ?? null;
        $this->contextProgramExerciseId = $data['programExerciseId'] ?? null;
        $this->contextUserId = $data['userId'] ?? null;
        $this->contextSessionGrouping = $data['sessionGrouping'] ?? [];

        $config = $data['config'] ?? [];
        $exerciseName = $data['exerciseName'] ?? __('Exercise');

        $formData = [
            'name' => $exerciseName,
            'config' => ApplyPerScope::prepareConfigForForm($config),
            'session_grouping' => $this->contextSessionGrouping,
        ];

        $this->open($formData, $exerciseName, $data['focusField'] ?? null);
    }

    public function open(
        array $data = [],
        ?string $title = null,
        ?string $focusField = null,
        ?int $focusIndex = null,
        array $formTypes = [],
        ?string $activeFormType = null,
        array $formTypeData = [],
    ): void
    {
        parent::open($data, $title, $focusField, $focusIndex, $formTypes, $activeFormType, $formTypeData);

        unset($this->fieldsets);
        $this->data = array_replace_recursive($this->buildDefaultsFromFieldsets(), $this->data);
        unset($this->fieldsets);
    }

    public function updatedDataConfigSettings(): void
    {
        unset($this->fieldsets);
        $settings = $this->data['config']['settings'];
        $this->data = array_replace_recursive($this->buildDefaultsFromFieldsets(), $this->data);
        $this->data['config']['settings'] = $settings;
        $this->data['config']['overrides'] = OverrideManager::reset();
    }

    public function updated(string $property, mixed $value): void
    {
        if ($property !== 'data.session_grouping.mode') {
            return;
        }

        $mode = (string) ($this->data['session_grouping']['mode'] ?? null);
        $this->data['session_grouping']['groupSize'] = SessionGroupingMode::defaultGroupSize($mode);
    }

    public function submit(): void
    {
        $this->validate($this->buildValidationRulesFromFieldsets(), [
            'required' => __('This field is required.'),
        ]);

        Flux::modal($this->name)->close();

        $this->dispatch('plan-exercise-settings.saved', data: [
            'config' => $this->data['config'],
            'session_grouping' => $this->data['session_grouping'] ?? [],
            'programExerciseId' => $this->contextProgramExerciseId,
            'exerciseId' => $this->contextExerciseId,
            'userId' => $this->contextUserId,
        ]);
    }

    public function render(): View
    {
        return view('livewire.training.view.plan-exercise-settings-form');
    }
}
