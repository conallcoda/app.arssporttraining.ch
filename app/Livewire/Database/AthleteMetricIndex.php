<?php

namespace App\Livewire\Database;

use App\Models\Users\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.admin')]
#[Title('ARS - Athlete Training // Metrics')]
class AthleteMetricIndex extends Component
{
    public User $athlete;

    public function mount(int $athleteId): void
    {
        $this->athlete = User::findOrFail($athleteId);
    }

    public function render()
    {
        return view('livewire.database.athlete-metric-index');
    }
}
