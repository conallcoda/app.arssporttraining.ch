<?php

namespace App\Livewire\Training;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.database')]
#[Title('ARS - Athlete Training // Program Categories')]
class ProgramCategoryIndex extends Component
{
    public function render()
    {
        return view('livewire.training.program-category-index');
    }
}
