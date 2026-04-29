<?php

namespace App\Livewire\Athlete;

use App\Data\Athlete\Metric\MetricEnum;
use App\Data\Athlete\Metric\Metrics\ReadinessMetric;
use App\Data\Athlete\Metric\MetricSubmissionData;
use App\Support\AthleteDashboardDate;
use App\Support\Readiness\ReadinessMetricService;
use App\Support\Readiness\ReadinessSurvey;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ReadinessCheck extends Component
{
    public array $form = [];

    public ?int $submissionId = null;

    public string $date;

    public function mount(string $date): void
    {
        abort_unless(AthleteDashboardDate::canSubmitReadinessForDate($date), 404);

        $this->date = $date;

        $service = app(ReadinessMetricService::class);
        $submission = $service->findSubmissionForDate((int) auth()->id(), $this->date);

        $this->submissionId = $submission?->id;
        $this->form = array_merge(
            ReadinessSurvey::defaultState(),
            $submission?->values->pluck('value', 'field')->all() ?? [],
        );

        if ($submission === null) {
            $this->form['restingHeartRateBaseline'] = $service->resolveBaseline(
                (int) auth()->id(),
                $this->date,
                $this->form['restingHeartRateBaseline'],
            );
        }
    }

    #[Computed]
    public function readinessViewData(): array
    {
        return ReadinessSurvey::buildViewData($this->form);
    }

    public function submitReadiness(): void
    {
        $submission = (new MetricSubmissionData(
            id: $this->submissionId,
            user_id: (int) auth()->id(),
            metric: MetricEnum::Readiness,
            recorded_by: (int) auth()->id(),
            recorded_at: $this->date,
            data: ReadinessMetric::from($this->form),
        ))->persist();

        $this->submissionId = $submission->id;
        $viewData = $this->readinessViewData;

        $this->dispatch(
            'readiness-updated',
            score: $viewData['readinessScore'],
            label: $viewData['trafficLightLabel'],
            color: $viewData['trafficLightColor'],
            date: $this->date,
        );

        Flux::modal('readiness-check')->close();
    }

    public function render(): View
    {
        return view('livewire.athlete.readiness-check');
    }
}
