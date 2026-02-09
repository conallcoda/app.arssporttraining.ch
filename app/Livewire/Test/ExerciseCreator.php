<?php

namespace App\Livewire\Test;

use App\Cms\Form\Form;
use App\Cms\Livewire\Concerns\InteractsWithFormData;
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

    public function mount(): void
    {
        $this->data = $this->buildDefaultsFromFieldsets();
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

    public function updatedDataSettings(): void
    {
        unset($this->fieldsets);
        $this->data = array_replace_recursive($this->buildDefaultsFromFieldsets(), $this->data);
    }

    public function render()
    {
        return view('livewire.test.exercise-creator');
    }
}
