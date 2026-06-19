<?php

namespace App\Livewire\Training\View;

use App\Data\Exercise\DropSet;
use App\Data\Exercise\ExerciseConfig;
use App\Data\Exercise\Preview\OverrideManager;
use App\Data\Exercise\Preview\SessionGroupingConfig;
use App\Support\Training\ApplyPerScope;
use Coda\Cms\Livewire\FormModal;
use Coda\FormKit\Form;
use Coda\FormKit\FormFieldsetGroup;
use Flux\Flux;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

class PlanExerciseSettingsForm extends FormModal
{
    public ?int $contextExerciseId = null;

    public ?int $contextProgramExerciseId = null;

    public ?int $contextUserId = null;

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
                'fields' => $this->sessionGroupingFields(),
                'prefix' => 'data.config.preview',
            ],
        ]);

        return $form;
    }

    private function sessionGroupingFields(): array
    {
        return SessionGroupingConfig::formFields(
            data: $this->data['config']['preview'] ?? [],
            modeField: 'groupingMode',
            modeLabel: 'Grouping',
        );
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

        $config = $data['config'] ?? [];
        $exerciseName = $data['exerciseName'] ?? __('Exercise');

        $formData = [
            'name' => $exerciseName,
            'config' => ApplyPerScope::prepareConfigForForm($config),
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
    ): void {
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

    public function updatedDataConfigSetsType(): void
    {
        unset($this->fieldsets);
    }

    public function submit(): void
    {
        $this->normalizeDropSetConfig();
        $this->normalizeCarryOverAthleteValuesConfig();

        try {
            $this->validate($this->buildValidationRulesFromFieldsets(), [
                'required' => __('This field is required.'),
            ], $this->buildValidationAttributesFromFieldsets());
        } catch (ValidationException $exception) {
            $this->selectFieldsetTabForValidationErrors($exception->validator->errors()->keys());

            throw $exception;
        }

        $this->data['config']['preview'] = SessionGroupingConfig::normalizeFormData(
            $this->data['config']['preview'] ?? [],
            'groupingMode',
        );

        Flux::modal($this->name)->close();

        $this->dispatch('plan-exercise-settings.saved', data: [
            'config' => $this->data['config'],
            'programExerciseId' => $this->contextProgramExerciseId,
            'exerciseId' => $this->contextExerciseId,
            'userId' => $this->contextUserId,
        ]);
    }

    private function normalizeCarryOverAthleteValuesConfig(): void
    {
        if (! isset($this->data['config']['weight']) || ! is_array($this->data['config']['weight'])) {
            return;
        }

        if (($this->data['config']['weight']['carryOverAthleteValues'] ?? true) === false) {
            $this->data['config']['weight']['carryOverAthleteValues'] = false;

            return;
        }

        unset($this->data['config']['weight']['carryOverAthleteValues']);
    }

    private function normalizeDropSetConfig(): void
    {
        if (! DropSet::isEnabled($this->data['config'] ?? [])) {
            return;
        }

        if (isset($this->data['config']['reps']) && is_array($this->data['config']['reps'])) {
            $this->data['config']['reps']['mode'] = 'manual';
        }

        if (isset($this->data['config']['weight']) && is_array($this->data['config']['weight'])) {
            $this->data['config']['weight']['mode'] = 'manual';
            $this->data['config']['weight']['oneRepMaxModifier'] = null;
        }
    }

    public function render(): View
    {
        return view('livewire.training.view.plan-exercise-settings-form');
    }
}
