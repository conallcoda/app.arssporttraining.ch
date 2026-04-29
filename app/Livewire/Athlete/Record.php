<?php

namespace App\Livewire\Athlete;

use App\Support\AthleteDashboardDate;
use App\Support\Readiness\ReadinessMetricService;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class Record extends Component
{
    public ?float $readinessScore = null;

    public ?string $readinessLabel = null;

    public ?string $readinessColor = null;

    public string $dashboardDate;

    #[Url(except: 'today', history: true)]
    public string $trainView = 'today';

    public function mount(): void
    {
        $this->dashboardDate = AthleteDashboardDate::todayDateString();
        $this->trainView = in_array($this->trainView, ['today', 'unrecorded'], true) ? $this->trainView : 'today';

        $presentation = app(ReadinessMetricService::class)->presentationForDate((int) auth()->id(), $this->dashboardDate);
        $this->readinessScore = $presentation['score'];
        $this->readinessLabel = $presentation['label'];
        $this->readinessColor = $presentation['color'];
    }

    #[On('readiness-updated')]
    public function onReadinessUpdated(float $score, ?string $label = null, ?string $color = null, ?string $date = null): void
    {
        if ($date !== null && $date !== $this->dashboardDate) {
            return;
        }

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
