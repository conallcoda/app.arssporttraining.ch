<?php

namespace App\Livewire\Database;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.database')]
#[Title('ARS - Athlete Training // Exercise Templates')]
class ExerciseTemplateIndex extends Component
{
    public function render()
    {
        return view('livewire.database.exercise-template-index');
    }
}
