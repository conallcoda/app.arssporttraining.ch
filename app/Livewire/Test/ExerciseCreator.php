<?php

namespace App\Livewire\Test;

use App\Cms\Form\Form;
use App\Cms\Livewire\Concerns\InteractsWithFormData;
use App\Livewire\Test\Data\Preview\ExercisePreviewBuilder;
use App\Livewire\Test\Data\Preview\GridOverrides;
use App\Livewire\Test\Data\Preview\PreviewGrid;
use App\Livewire\Test\Data\Preview\StrategyOrchestrator;
use App\Livewire\Test\Data\Strategies\Weight\MeasuredData;
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

    private const SESSIONS_PER_WEEK = 2;

    public array $data = [];

    public string $activeTab = 'preview';

    public ?int $measuredReps = 8;

    public ?float $measuredWeight = 52;

    public ?int $targetGoal = 7;

    public function mount(): void
    {
        $this->data = $this->buildDefaultsFromFieldsets();
        unset($this->fieldsets);
        $this->data = array_replace_recursive($this->buildDefaultsFromFieldsets(), $this->data);
    }

    #[Computed]
    public function formConfig(): Form
    {
        $definition = TestExerciseData::getForm();

        return $definition instanceof Form ? $definition : Form::fields($definition);
    }

    #[Computed]
    public function fieldsets(): array
    {
        return $this->formConfig->resolveFieldsets($this->data);
    }

    #[Computed]
    public function previewGrid(): PreviewGrid
    {
        $measuredData = new MeasuredData(
            measuredReps: $this->measuredReps,
            measuredWeight: $this->measuredWeight,
            targetGoal: $this->targetGoal,
        );

        $overrides = GridOverrides::fromArrays(
            $this->data['overrides']['cells'] ?? [],
            $this->data['overrides']['weeks'] ?? [],
        );

        return ExercisePreviewBuilder::build($this->data, $measuredData, 5, $overrides, self::SESSIONS_PER_WEEK);
    }

    public function updateCellOverride(int $weekIndex, int $setIndex, string $field, mixed $value): void
    {
        $defaultValue = $this->getDefaultCellValue($field, $weekIndex, $setIndex);
        $valuesMatch = $this->cellValuesMatch($value, $defaultValue);

        for ($s = 0; $s < self::SESSIONS_PER_WEEK; $s++) {
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

    public function render()
    {
        return view('livewire.test.exercise-creator');
    }

    private function getDefaultCellValue(string $field, int $weekIndex, int $setIndex): mixed
    {
        $measuredData = new MeasuredData(
            measuredReps: $this->measuredReps,
            measuredWeight: $this->measuredWeight,
            targetGoal: $this->targetGoal,
        );

        $orchestrator = new StrategyOrchestrator($this->data, $measuredData, 5);
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
