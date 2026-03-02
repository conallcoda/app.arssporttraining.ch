<?php

namespace App\Livewire\Training;

use App\Models\Exercise\ExerciseProgram;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.database')]
#[Title('ARS - Athlete Training // Program')]
class ExerciseProgramView extends Component
{
    public ExerciseProgram $exerciseProgram;

    public function mount(ExerciseProgram $exerciseProgram): void
    {
        $this->exerciseProgram = $exerciseProgram;
    }

    public function updateName(string $name): void
    {
        $this->exerciseProgram->name = $name;
        $this->exerciseProgram->save();
    }

    public function render()
    {
        return view('livewire.training.exercise-program-view');
    }
}
