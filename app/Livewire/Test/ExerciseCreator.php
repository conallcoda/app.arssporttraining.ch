<?php

namespace App\Livewire\Test;

use App\Cms\Form\Form;
use App\Cms\Livewire\Concerns\InteractsWithFormData;
use App\Livewire\Test\Data\Preview\ExercisePreviewBuilder;
use App\Livewire\Test\Data\Preview\GridOverrides;
use App\Livewire\Test\Data\Preview\PreviewGrid;
use App\Livewire\Test\Data\Preview\StrategyOrchestrator;
use App\Livewire\Test\Data\Settings\PreviewSetting;
use App\Livewire\Test\Data\Settings\WeightProgressionSetting;
use App\Livewire\Test\Data\TestExerciseData;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.database')]
#[Title('ARS - Athlete Training // Exercise Creator')]
class ExerciseCreator extends Component
{
    use InteractsWithFormData;

    private const DEFAULT_WEEKS = 5;

    private const DEFAULT_SESSIONS_PER_WEEK = 1;

    public array $data = [];

    public string $activeTab = 'preview';

    public function mount(): void
    {
        $this->data = $this->buildDefaultsFromFieldsets();
        unset($this->fieldsets);
        $this->data = array_replace_recursive($this->buildDefaultsFromFieldsets(), $this->data);

        $this->data['progression'] = array_merge($this->data['progression'] ?? [], [
            'weeks' => self::DEFAULT_WEEKS,
            'sessionsPerWeek' => self::DEFAULT_SESSIONS_PER_WEEK,
            'measuredReps' => 8,
            'measuredWeight' => 52,
            'targetGoal' => 7,
        ]);

        unset($this->fieldsets);
    }

    #[Computed]
    public function formConfig(): Form
    {
        $definition = TestExerciseData::getForm();
        $form = $definition instanceof Form ? $definition : Form::fields($definition);

        $form->fieldset(
            'Preview',
            fn (array $data) => ['fields' => PreviewSetting::fields($data), 'prefix' => 'data.progression'],
        );
        $form->appendToFieldsetTabs('Settings', ['Preview']);

        return $form;
    }

    #[Computed]
    public function fieldsets(): array
    {
        return $this->formConfig->resolveFieldsets($this->data);
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
            $this->data['overrides']['cells'] ?? [],
            $this->data['overrides']['weeks'] ?? [],
        );

        $weeks = (int) ($progression['weeks'] ?? self::DEFAULT_WEEKS);
        $sessionsPerWeek = (int) ($progression['sessionsPerWeek'] ?? self::DEFAULT_SESSIONS_PER_WEEK);

        return ExercisePreviewBuilder::build($this->data, $measuredData, $weeks, $overrides, $sessionsPerWeek);
    }

    public function updateCellOverride(int $weekIndex, int $setIndex, string $field, mixed $value, int $session, bool $applyToAll = false): void
    {
        $defaultValue = $this->getDefaultCellValue($field, $weekIndex, $setIndex);
        $valuesMatch = $this->cellValuesMatch($value, $defaultValue);

        $sessionsPerWeek = (int) ($this->data['progression']['sessionsPerWeek'] ?? self::DEFAULT_SESSIONS_PER_WEEK);
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
        $config = $this->data[$field] ?? [];
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
        $this->data['overrides'] = ['cells' => [], 'weeks' => []];
        unset($this->previewGrid);
    }

    public function updatedDataSettings(): void
    {
        unset($this->fieldsets);
        unset($this->previewGrid);
        $settings = $this->data['settings'];
        $this->data = array_replace_recursive($this->buildDefaultsFromFieldsets(), $this->data);
        $this->data['settings'] = $settings;
        $this->data['overrides'] = ['cells' => [], 'weeks' => []];
    }

    public function updatedDataProgression(): void
    {
        unset($this->previewGrid);
    }

    public function render()
    {
        return view('livewire.test.exercise-creator');
    }

    private function getDefaultCellValue(string $field, int $weekIndex, int $setIndex): mixed
    {
        $progression = $this->data['progression'] ?? [];
        $measuredData = new WeightProgressionSetting(
            measuredReps: $progression['measuredReps'] ?? null,
            measuredWeight: $progression['measuredWeight'] ?? null,
            targetGoal: $progression['targetGoal'] ?? null,
        );

        $weeks = (int) ($progression['weeks'] ?? self::DEFAULT_WEEKS);
        $orchestrator = new StrategyOrchestrator($this->data, $measuredData, $weeks);
        $state = $orchestrator->execute();

        return $state->getCellValue($field, $weekIndex, $setIndex);
    }

    private function setCellOverride(int $week, int $session, int $set, string $field, mixed $value): void
    {
        $cells = $this->data['overrides']['cells'] ?? [];

        foreach ($cells as $index => $override) {
            if ($override['week'] === $week && $override['session'] === $session && $override['set'] === $set) {
                $this->data['overrides']['cells'][$index]['data'][$field] = $value;

                return;
            }
        }

        $this->data['overrides']['cells'][] = [
            'week' => $week,
            'session' => $session,
            'set' => $set,
            'data' => [$field => $value],
        ];
    }

    private function removeCellOverride(int $week, int $session, int $set, string $field): void
    {
        $cells = $this->data['overrides']['cells'] ?? [];

        foreach ($cells as $index => $override) {
            if ($override['week'] === $week && $override['session'] === $session && $override['set'] === $set && isset($override['data'][$field])) {
                unset($this->data['overrides']['cells'][$index]['data'][$field]);

                if (empty($this->data['overrides']['cells'][$index]['data'])) {
                    array_splice($this->data['overrides']['cells'], $index, 1);
                }

                return;
            }
        }
    }

    private function setWeekOverride(int $week, string $field, mixed $value): void
    {
        $weeks = $this->data['overrides']['weeks'] ?? [];

        foreach ($weeks as $index => $override) {
            if ($override['week'] === $week) {
                $this->data['overrides']['weeks'][$index]['data'][$field] = $value;

                return;
            }
        }

        $this->data['overrides']['weeks'][] = [
            'week' => $week,
            'data' => [$field => $value],
        ];
    }

    private function removeWeekOverride(int $week, string $field): void
    {
        $weeks = $this->data['overrides']['weeks'] ?? [];

        foreach ($weeks as $index => $override) {
            if ($override['week'] === $week && isset($override['data'][$field])) {
                unset($this->data['overrides']['weeks'][$index]['data'][$field]);

                if (empty($this->data['overrides']['weeks'][$index]['data'])) {
                    array_splice($this->data['overrides']['weeks'], $index, 1);
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
