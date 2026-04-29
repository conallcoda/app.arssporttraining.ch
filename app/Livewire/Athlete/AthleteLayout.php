<?php

namespace App\Livewire\Athlete;

use App\Support\AthleteDashboardDate;
use App\Support\Readiness\ReadinessMetricService;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class AthleteLayout extends Component
{
    public ?float $readinessScore = null;

    public int $readinessModalOpened = 0;

    public string $readinessDate;

    public function mount(): void
    {
        $this->readinessDate = AthleteDashboardDate::todayDateString();

        $presentation = app(ReadinessMetricService::class)->presentationForDate(
            (int) auth()->id(),
            $this->readinessDate,
        );

        $this->readinessScore = $presentation['score'];
    }

    #[On('open-readiness-modal')]
    public function onOpenReadinessModal(?string $date = null): void
    {
        $resolvedDate = $date ?? AthleteDashboardDate::todayDateString();

        if (! AthleteDashboardDate::canSubmitReadinessForDate($resolvedDate)) {
            return;
        }

        $this->readinessDate = $resolvedDate;
        $this->readinessModalOpened++;

        Flux::modal('readiness-check')->show();
    }

    #[On('readiness-updated')]
    public function onReadinessUpdated(float $score, ?string $date = null): void
    {
        if (($date ?? AthleteDashboardDate::todayDateString()) !== AthleteDashboardDate::todayDateString()) {
            return;
        }

        $this->readinessScore = $score;
    }

    #[On('readiness-submitted')]
    public function onReadinessSubmitted(float $score): void
    {
        $this->readinessScore = $score;
        $this->dispatch('readiness-updated', score: $score, label: null, color: null);
    }

    public function readinessLabel(): ?string
    {
        return null;
    }

    public function readinessColor(): ?string
    {
        return null;
    }

    public function render(): View
    {
        return view('livewire.athlete.athlete-layout', [
            'readinessLabel' => $this->readinessLabel(),
            'readinessColor' => $this->readinessColor(),
        ]);
    }
}
