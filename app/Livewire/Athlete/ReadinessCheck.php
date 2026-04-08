<?php

namespace App\Livewire\Athlete;

use Flux\Flux;
use Illuminate\View\View;
use Livewire\Component;

class ReadinessCheck extends Component
{
    public int $score = 2;

    public function submitReadiness(): void
    {
        $this->dispatch('readiness-submitted', score: $this->score);

        Flux::modal('readiness-check')->close();
    }

    public function render(): View
    {
        return view('livewire.athlete.readiness-check');
    }
}
