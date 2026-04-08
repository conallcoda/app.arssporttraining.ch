<?php

namespace App\Livewire\Athlete;

use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class AthleteLayout extends Component
{
    public ?int $readinessScore = null;

    public int $readinessModalOpened = 0;

    #[On('open-readiness-modal')]
    public function onOpenReadinessModal(): void
    {
        $this->readinessModalOpened++;

        Flux::modal('readiness-check')->show();
    }

    #[On('readiness-submitted')]
    public function onReadinessSubmitted(int $score): void
    {
        $this->readinessScore = $score;

        $this->dispatch('readiness-updated', score: $score, label: $this->readinessLabel(), color: $this->readinessColor());
    }

    public function readinessLabel(): ?string
    {
        return match ($this->readinessScore) {
            4 => 'Ready',
            3 => 'Train Smart',
            2 => 'Recovery',
            1 => 'Rest',
            default => null,
        };
    }

    public function readinessColor(): ?string
    {
        return match ($this->readinessScore) {
            4 => 'green',
            3 => 'amber',
            2 => 'orange',
            1 => 'red',
            default => null,
        };
    }

    public function render(): View
    {
        return view('livewire.athlete.athlete-layout', [
            'readinessLabel' => $this->readinessLabel(),
            'readinessColor' => $this->readinessColor(),
        ]);
    }
}
