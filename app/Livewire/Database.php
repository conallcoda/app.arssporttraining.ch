<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.database')]
#[Title('ARS - Athlete Training // Database')]
class Database extends Component
{
    public function render()
    {
        return view('livewire.database');
    }
}
