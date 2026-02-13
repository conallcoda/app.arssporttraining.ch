<?php

namespace App\Livewire\Database;

use App\Cms\Form\Form;
use App\Data\Exercise\ExerciseData;
use App\Data\Exercise\Preview\ExercisePreviewBuilder;
use App\Data\Exercise\Preview\GridOverrides;
use App\Data\Exercise\Preview\PreviewGrid;
use App\Data\Exercise\Preview\StrategyOrchestrator;
use App\Data\Exercise\Settings\PreviewSetting;
use App\Data\Exercise\Settings\WeightProgressionSetting;
use App\Livewire\Cms\FormModal;
use Illuminate\View\View;
use Livewire\Attributes\Computed;

class ExerciseForm extends FormModal
{
    public int $defaultWeeks = 5;

    public int $defaultSessionsPerWeek = 1;

    public bool $showPreview = true;

    public bool $showData = false;

    public string $activeTab = 'preview';

    public function mount(
        string $name = 'exercise-form',
        string $title = 'Exercise',
        ?string $formDataClass = null,
        string $submitLabel = 'Save',
        string $cancelLabel = 'Cancel',
        bool $flyout = true,
        string $maxWidth = 'max-w-[83.333%] overflow-x-hidden',
        int $defaultWeeks = 5,
        int $defaultSessionsPerWeek = 1,
        bool $showPreview = true,
        bool $showData = true,
    ): void {
        $this->defaultWeeks = $defaultWeeks;
        $this->defaultSessionsPerWeek = $defaultSessionsPerWeek;
        $this->showPreview = $showPreview;
        $this->showData = $showData;

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

        $this->applyProgressionDefaults();
        unset($this->fieldsets);
    }

    public function open(array $data = [], ?string $title = null, ?string $focusField = null, ?int $focusIndex = null): void
    {
        parent::open($data, $title, $focusField, $focusIndex);

        unset($this->fieldsets);
        $this->data = array_replace_recursive($this->buildDefaultsFromFieldsets(), $this->data);

        if (empty($data)) {
            $this->applyProgressionDefaults();
        }

        unset($this->fieldsets);
    }

    #[Computed]
    public function formConfig(): Form
    {
        $definition = ExerciseData::getForm();
        $form = $definition instanceof Form ? $definition : Form::fields($definition);

        $form->fieldset(
            'Preview',
            fn (array $data) => ['fields' => PreviewSetting::fields($data), 'prefix' => 'data.progression'],
        );
        $form->appendToFieldsetTabs('Settings', ['Preview']);

        return $form;
    }

    #[Computed]
    public function previewGrid(): PreviewGrid
    {
        $progression = $this->data['progression'] ?? [];
        $measuredData = new WeightProgressionSetting(
            measuredReps: $progression['measuredReps'] ?? null,
            measuredWeight: $progression['measuredWeight'] ?? null,
            targetGoal: $progression['targetGoal'] ?? null,
        );

        $overrides = GridOverrides::fromArrays(
            $this->data['config']['overrides']['cells'] ?? [],
            $this->data['config']['overrides']['weeks'] ?? [],
        );

        $weeks = (int) ($progression['weeks'] ?? $this->defaultWeeks);
        $sessionsPerWeek = (int) ($progression['sessionsPerWeek'] ?? $this->defaultSessionsPerWeek);

        return ExercisePreviewBuilder::build($this->data['config'], $measuredData, $weeks, $overrides, $sessionsPerWeek);
    }

    public function updateCellOverride(int $weekIndex, int $setIndex, string $field, mixed $value, int $session, bool $applyToAll = false): void
    {
        $defaultValue = $this->getDefaultCellValue($field, $weekIndex, $setIndex);
        $valuesMatch = $this->cellValuesMatch($value, $defaultValue);

        $sessionsPerWeek = (int) ($this->data['progression']['sessionsPerWeek'] ?? $this->defaultSessionsPerWeek);
        $sessions = $applyToAll ? range(0, $sessionsPerWeek - 1) : [$session];

        foreach ($sessions as $s) {
            if ($valuesMatch) {
                $this->removeCellOverride($weekIndex, $s, $setIndex, $field);
            } else {
                $this->setCellOverride($weekIndex, $s, $setIndex, $field, $value);
            }
        }

        unset($this->previewGrid);
    }

    public function updateWeekOverride(int $weekIndex, string $field, mixed $value): void
    {
        $config = $this->data['config'][$field] ?? [];
        $defaultValue = $config['default'] ?? null;
        $valuesMatch = $this->weekValuesMatch($value, $defaultValue, $field);

        if ($valuesMatch) {
            $this->removeWeekOverride($weekIndex, $field);
        } else {
            $this->setWeekOverride($weekIndex, $field, $value);
        }

        unset($this->previewGrid);
    }

    public function resetOverrides(): void
    {
        $this->data['config']['overrides'] = ['cells' => [], 'weeks' => []];
        unset($this->previewGrid);
    }

    public function updatedDataConfigSettings(): void
    {
        unset($this->fieldsets);
        unset($this->previewGrid);
        $settings = $this->data['config']['settings'];
        $this->data = array_replace_recursive($this->buildDefaultsFromFieldsets(), $this->data);
        $this->data['config']['settings'] = $settings;
        $this->data['config']['overrides'] = ['cells' => [], 'weeks' => []];
    }

    public function updatedDataProgression(): void
    {
        unset($this->previewGrid);
    }

    public function render(): View
    {
        return view('livewire.database.exercise-form');
    }

    private function applyProgressionDefaults(): void
    {
        $this->data['progression'] = array_merge($this->data['progression'] ?? [], [
            'weeks' => $this->defaultWeeks,
            'sessionsPerWeek' => $this->defaultSessionsPerWeek,
            'measuredReps' => 8,
            'measuredWeight' => 52,
            'targetGoal' => 7,
        ]);
    }

    private function getDefaultCellValue(string $field, int $weekIndex, int $setIndex): mixed
    {
        $progression = $this->data['progression'] ?? [];
        $measuredData = new WeightProgressionSetting(
            measuredReps: $progression['measuredReps'] ?? null,
            measuredWeight: $progression['measuredWeight'] ?? null,
            targetGoal: $progression['targetGoal'] ?? null,
        );

        $weeks = (int) ($progression['weeks'] ?? $this->defaultWeeks);
        $orchestrator = new StrategyOrchestrator($this->data['config'], $measuredData, $weeks);
        $state = $orchestrator->execute();

        return $state->getCellValue($field, $weekIndex, $setIndex);
    }

    private function setCellOverride(int $week, int $session, int $set, string $field, mixed $value): void
    {
        $cells = $this->data['config']['overrides']['cells'] ?? [];

        foreach ($cells as $index => $override) {
            if ($override['week'] === $week && $override['session'] === $session && $override['set'] === $set) {
                $this->data['config']['overrides']['cells'][$index]['data'][$field] = $value;

                return;
            }
        }

        $this->data['config']['overrides']['cells'][] = [
            'week' => $week,
            'session' => $session,
            'set' => $set,
            'data' => [$field => $value],
        ];
    }

    private function removeCellOverride(int $week, int $session, int $set, string $field): void
    {
        $cells = $this->data['config']['overrides']['cells'] ?? [];

        foreach ($cells as $index => $override) {
            if ($override['week'] === $week && $override['session'] === $session && $override['set'] === $set && isset($override['data'][$field])) {
                unset($this->data['config']['overrides']['cells'][$index]['data'][$field]);

                if (empty($this->data['config']['overrides']['cells'][$index]['data'])) {
                    array_splice($this->data['config']['overrides']['cells'], $index, 1);
                }

                return;
            }
        }
    }

    private function setWeekOverride(int $week, string $field, mixed $value): void
    {
        $weeks = $this->data['config']['overrides']['weeks'] ?? [];

        foreach ($weeks as $index => $override) {
            if ($override['week'] === $week) {
                $this->data['config']['overrides']['weeks'][$index]['data'][$field] = $value;

                return;
            }
        }

        $this->data['config']['overrides']['weeks'][] = [
            'week' => $week,
            'data' => [$field => $value],
        ];
    }

    private function removeWeekOverride(int $week, string $field): void
    {
        $weeks = $this->data['config']['overrides']['weeks'] ?? [];

        foreach ($weeks as $index => $override) {
            if ($override['week'] === $week && isset($override['data'][$field])) {
                unset($this->data['config']['overrides']['weeks'][$index]['data'][$field]);

                if (empty($this->data['config']['overrides']['weeks'][$index]['data'])) {
                    array_splice($this->data['config']['overrides']['weeks'], $index, 1);
                }

                return;
            }
        }
    }

    private function cellValuesMatch(mixed $value, mixed $originalValue): bool
    {
        if ($originalValue === null) {
            return false;
        }

        if (is_string($value) || is_string($originalValue)) {
            return (string) $value === (string) $originalValue;
        }

        return abs((float) $value - (float) $originalValue) < 0.001;
    }

    private function weekValuesMatch(mixed $value, mixed $originalValue, string $field): bool
    {
        if ($originalValue === null) {
            return false;
        }

        if (in_array($field, ['tempo', 'pace'])) {
            return (string) $value === (string) $originalValue;
        }

        return abs((float) $value - (float) $originalValue) < 0.001;
    }
}
