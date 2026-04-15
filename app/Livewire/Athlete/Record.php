<?php

namespace App\Livewire\Athlete;

use App\Support\AthleteDashboardDate;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class Record extends Component
{
    public ?int $readinessScore = null;

    public ?string $readinessLabel = null;

    public ?string $readinessColor = null;

    public string $dashboardDate;

    #[Url(except: 'today', history: true)]
    public string $trainView = 'today';

    public function mount(): void
    {
        $this->dashboardDate = AthleteDashboardDate::todayDateString();
        $this->trainView = in_array($this->trainView, ['today', 'unrecorded'], true) ? $this->trainView : 'today';
    }

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
            ->layout('components.layouts.athlete', ['title' => 'Train']);
    }
}
