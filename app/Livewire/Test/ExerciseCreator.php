<?php

namespace App\Livewire\Test;

use App\Cms\Form\Form;
use App\Cms\Livewire\Concerns\InteractsWithFormData;
use App\Livewire\Test\Data\Preview\ExercisePreviewBuilder;
use App\Livewire\Test\Data\Preview\PreviewGrid;
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

        return ExercisePreviewBuilder::build($this->data, $measuredData);
    }

    public function updatedDataSettings(): void
    {
        unset($this->fieldsets);
        unset($this->previewGrid);
        $settings = $this->data['settings'];
        $this->data = array_replace_recursive($this->buildDefaultsFromFieldsets(), $this->data);
        $this->data['settings'] = $settings;
    }

    public function render()
    {
        return view('livewire.test.exercise-creator');
    }
}
