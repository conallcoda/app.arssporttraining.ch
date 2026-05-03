<?php

namespace App\Livewire\Training;

use App\Models\Exercise\ExerciseProgram;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ExerciseProgramView extends Component
{
    public int $exerciseProgramId;

    public function mount(ExerciseProgram $exerciseProgram): void
    {
        $this->exerciseProgramId = $exerciseProgram->id;
    }

    #[Computed]
    public function exerciseProgram(): ExerciseProgram
    {
        return ExerciseProgram::query()->findOrFail($this->exerciseProgramId);
    }

    public function updateName(string $name): void
    {
        $this->exerciseProgram->name = $name;
        $this->exerciseProgram->save();
    }

    public function render()
    {
        return view('livewire.training.exercise-program-view', [
            'exerciseProgram' => $this->exerciseProgram,
        ]);
    }
}
