<?php

namespace App\Livewire\Athlete;

use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class Record extends Component
{
    public ?int $readinessScore = null;

    public ?string $readinessLabel = null;

    public ?string $readinessColor = null;

    public function mount(): void {}

    #[On('readiness-updated')]
    public function onReadinessUpdated(int $score, ?string $label = null, ?string $color = null): void
    {
        $this->readinessScore = $score;
        $this->readinessLabel = $label;
        $this->readinessColor = $color;
    }

    public function render(): View
    {
        return view('livewire.athlete.record')
            ->layout('components.layouts.athlete', ['title' => 'Record']);
    }
}
