<?php

namespace App\Livewire\Training\View;

use App\Data\Exercise\Preview\SessionGroupingConfig;
use App\Data\Exercise\Preview\SessionGroupingMode;
use Coda\Cms\Livewire\FormModal;
use Coda\FormKit\Form;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

class PlanExerciseGroupingForm extends FormModal
{
    public ?int $contextExerciseId = null;

    public ?int $contextProgramExerciseId = null;

    public ?int $contextUserId = null;

    public bool $hasOverride = false;

    public function mount(
        string $name = 'plan-exercise-grouping',
        string $title = 'exercise-grouping-default',
        ?string $formDataClass = null,
        string $submitLabel = 'save-default',
        string $cancelLabel = 'cancel-default',
        bool $flyout = true,
        string $maxWidth = 'max-w-lg',
        bool $showDelete = false,
        array $contextData = [],
        array $excludeFields = [],
    ): void {
        parent::mount(
            name: $name,
            title: $title === 'exercise-grouping-default' ? __('Exercise Grouping') : $title,
            formDataClass: $formDataClass,
            submitLabel: $submitLabel === 'save-default' ? __('Save') : $submitLabel,
            cancelLabel: $cancelLabel === 'cancel-default' ? __('Cancel') : $cancelLabel,
            flyout: $flyout,
            maxWidth: $maxWidth,
            showDelete: $showDelete,
            contextData: $contextData,
            excludeFields: $excludeFields,
        );

        unset($this->fieldsets);
        $this->data = array_replace_recursive($this->buildDefaultsFromFieldsets(), $this->data);
        unset($this->fieldsets);
    }

    #[Computed]
    public function formConfig(): Form
    {
        return Form::make()
            ->fieldset('Session Grouping', SessionGroupingConfig::fields($this->data['session_grouping'] ?? []), prefix: 'data.session_grouping');
    }

    #[Computed]
    public function fieldsets(): array
    {
        return $this->formConfig->resolveFieldsets($this->data);
    }

    #[On('open-plan-exercise-grouping')]
    public function openForExercise(array $data): void
    {
        $this->contextExerciseId = $data['exerciseId'] ?? null;
        $this->contextProgramExerciseId = $data['programExerciseId'] ?? null;
        $this->contextUserId = $data['userId'] ?? null;
        $this->hasOverride = (bool) ($data['has_override'] ?? false);

        $formData = [
            'name' => $data['exerciseName'] ?? __('Exercise'),
            'session_grouping' => $data['session_grouping'] ?? [],
        ];

        $this->open($formData, $data['exerciseName'] ?? __('Exercise'));
    }

    public function open(array $data = [], ?string $title = null, ?string $focusField = null, ?int $focusIndex = null): void
    {
        parent::open($data, $title, $focusField, $focusIndex);

        unset($this->fieldsets);
        $this->data = array_replace_recursive($this->buildDefaultsFromFieldsets(), $this->data);
        unset($this->fieldsets);
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
        $rules = $this->buildValidationRulesFromFieldsets();

        if (! empty($rules)) {
            $this->validate($rules, [
                'required' => __('This field is required.'),
            ]);
        }

        Flux::modal($this->name)->close();

        $this->dispatch('plan-exercise-grouping.saved', data: [
            'session_grouping' => $this->data['session_grouping'] ?? [],
            'programExerciseId' => $this->contextProgramExerciseId,
            'exerciseId' => $this->contextExerciseId,
            'userId' => $this->contextUserId,
        ]);
    }

    public function resetOverride(): void
    {
        Flux::modal($this->name)->close();

        $this->dispatch('plan-exercise-grouping.reset', data: [
            'programExerciseId' => $this->contextProgramExerciseId,
            'exerciseId' => $this->contextExerciseId,
            'userId' => $this->contextUserId,
        ]);
    }

    public function render(): View
    {
        return view('livewire.training.view.plan-exercise-grouping-form');
    }
}
