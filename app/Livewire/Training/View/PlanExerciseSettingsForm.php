<?php

namespace App\Livewire\Training\View;

use App\Data\Exercise\ExerciseConfig;
use App\Data\Exercise\Preview\ExercisePreviewBuilder;
use App\Data\Exercise\Preview\GridOverrides;
use App\Data\Exercise\Preview\OverrideManager;
use App\Data\Exercise\Preview\PreviewGrid;
use App\Data\Exercise\Settings\PreviewSetting;
use App\Data\Exercise\Settings\WeightProgressionSetting;
use Coda\Cms\Form\Form;
use Coda\Cms\Livewire\FormModal;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

class PlanExerciseSettingsForm extends FormModal
{
    public int $defaultWeeks = 5;

    public int $defaultSessionsPerWeek = 1;

    public ?int $contextPivotId = null;

    public ?int $contextUserId = null;

    public function mount(
        string $name = 'plan-exercise-settings',
        string $title = 'Exercise Settings',
        ?string $formDataClass = null,
        string $submitLabel = 'Save',
        string $cancelLabel = 'Cancel',
        bool $flyout = true,
        string $maxWidth = 'max-w-[83.333%] overflow-x-hidden',
    ): void {
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
        unset($this->fieldsets);
    }

    #[Computed]
    public function formConfig(): Form
    {
        $form = Form::make();
        ExerciseConfig::addFormFieldsets($form);

        $form->fieldset(
            'Preview',
            fn (array $data) => ['fields' => PreviewSetting::fields($data), 'prefix' => 'data.config.preview'],
        );
        $form->appendToFieldsetTabs('Settings', ['Preview']);

        return $form;
    }

    #[On('open-plan-exercise-settings')]
    public function openForExercise(array $data): void
    {
        $this->contextPivotId = $data['pivotId'] ?? null;
        $this->contextUserId = $data['userId'] ?? null;

        $config = $data['config'] ?? [];
        $exerciseName = $data['exerciseName'] ?? 'Exercise';

        $formData = [
            'name' => $exerciseName,
            'config' => $config,
        ];

        $this->open($formData, $exerciseName);
    }

    public function open(array $data = [], ?string $title = null, ?string $focusField = null, ?int $focusIndex = null): void
    {
        parent::open($data, $title, $focusField, $focusIndex);

        unset($this->fieldsets);
        $this->data = array_replace_recursive($this->buildDefaultsFromFieldsets(), $this->data);

        if (empty($this->data['config']['preview'])) {
            $this->data['config']['preview'] = [
                'weeks' => $this->defaultWeeks,
                'sessionsPerWeek' => $this->defaultSessionsPerWeek,
                'measuredReps' => 1,
                'measuredWeight' => 50,
                'targetGoal' => 10,
            ];
        }

        unset($this->fieldsets);
    }

    #[Computed]
    public function previewGrid(): PreviewGrid
    {
        $preview = $this->data['config']['preview'] ?? [];
        $measuredData = new WeightProgressionSetting(
            measuredReps: $preview['measuredReps'] ?? null,
            measuredWeight: $preview['measuredWeight'] ?? null,
            targetGoal: $preview['targetGoal'] ?? null,
        );

        $overrides = GridOverrides::fromArrays(
            $this->data['config']['overrides']['cells'] ?? [],
            $this->data['config']['overrides']['weeks'] ?? [],
        );

        $weeks = (int) ($preview['weeks'] ?? $this->defaultWeeks);
        $sessionsPerWeek = (int) ($preview['sessionsPerWeek'] ?? $this->defaultSessionsPerWeek);

        return ExercisePreviewBuilder::build($this->data['config'], $measuredData, $weeks, $overrides, $sessionsPerWeek);
    }

    public function updateCellOverride(int $weekIndex, int $setIndex, string $field, mixed $value, int $session, bool $applyToAll = false): void
    {
        $this->data['config']['overrides'] = OverrideManager::updateCellOverride(
            $this->data['config']['overrides'] ?? OverrideManager::reset(),
            $this->data['config'],
            $this->defaultWeeks,
            $this->defaultSessionsPerWeek,
            $weekIndex,
            $setIndex,
            $field,
            $value,
            $session,
            $applyToAll,
        );

        unset($this->previewGrid);
    }

    public function updateWeekOverride(int $weekIndex, string $field, mixed $value): void
    {
        $this->data['config']['overrides'] = OverrideManager::updateWeekOverride(
            $this->data['config']['overrides'] ?? OverrideManager::reset(),
            $this->data['config'],
            $weekIndex,
            $field,
            $value,
        );

        unset($this->previewGrid);
    }

    public function resetOverrides(): void
    {
        $this->data['config']['overrides'] = OverrideManager::reset();
        unset($this->previewGrid);
    }

    public function updatedDataConfig(): void
    {
        unset($this->previewGrid);
    }

    public function updatedDataConfigSettings(): void
    {
        unset($this->fieldsets);
        unset($this->previewGrid);
        $settings = $this->data['config']['settings'];
        $this->data = array_replace_recursive($this->buildDefaultsFromFieldsets(), $this->data);
        $this->data['config']['settings'] = $settings;
        $this->data['config']['overrides'] = OverrideManager::reset();
    }

    public function updatedDataConfigPreview(): void
    {
        unset($this->previewGrid);
    }

    public function submit(): void
    {
        $this->validate($this->buildValidationRulesFromFieldsets(), [
            'required' => 'This field is required.',
        ]);

        Flux::modal($this->name)->close();

        $this->dispatch('plan-exercise-settings.saved', data: [
            'config' => $this->data['config'],
            'pivotId' => $this->contextPivotId,
            'userId' => $this->contextUserId,
        ]);
    }

    public function render(): View
    {
        return view('livewire.training.view.plan-exercise-settings-form');
    }
}
