<?php

namespace App\Livewire\Test;

use App\Data\Athlete\Metric\ReadinessMetricData;
use Coda\Cms\Livewire\CmsPage;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ReadinessForm extends Component
{
    public string $sleepDuration = '7:45';

    public int $sleepQuality = 3;

    public int $altitudeMeters = 0;

    public int $condition = 3;

    public int $mood = 3;

    public int $motivation = 3;

    public int $soreness = 1;

    public int $energy = 3;

    public int $restingHeartRate = 60;

    public int $restingHeartRateBaseline = 60;

    public ?int $hrv = null;

    #[Computed]
    public function data(): ReadinessMetricData
    {
        return new ReadinessMetricData(
            sleepMinutes: ReadinessMetricData::parseSleepDuration($this->sleepDuration),
            sleepQuality: $this->sleepQuality,
            altitudeMeters: $this->altitudeMeters,
            condition: $this->condition,
            mood: $this->mood,
            motivation: $this->motivation,
            soreness: $this->soreness,
            energy: $this->energy,
            restingHeartRate: $this->restingHeartRate,
            restingHeartRateBaseline: $this->restingHeartRateBaseline,
            hrv: $this->hrv,
        );
    }

    #[Computed]
    public function sleepDurationScore(): ?int
    {
        return ReadinessMetricData::sleepDurationScore(
            ReadinessMetricData::parseSleepDuration($this->sleepDuration),
        );
    }

    #[Computed]
    public function altitudeScore(): ?int
    {
        return ReadinessMetricData::altitudeScore($this->altitudeMeters);
    }

    #[Computed]
    public function sorenessScore(): ?int
    {
        return ReadinessMetricData::sorenessScore($this->soreness);
    }

    #[Computed]
    public function rhrScore(): ?int
    {
        return ReadinessMetricData::rhrScore($this->restingHeartRate, $this->restingHeartRateBaseline);
    }

    #[Computed]
    public function sleepScore(): ?float
    {
        return $this->data->sleepScore();
    }

    #[Computed]
    public function readinessScore(): ?float
    {
        return $this->data->readinessScore();
    }

    #[Computed]
    public function trafficLight(): ?string
    {
        return $this->data->trafficLight();
    }

    public function render()
    {
        return view('livewire.test.readiness-form')
            ->layout(CmsPage::layout())
            ->title(CmsPage::buildTitle(__('Readiness')));
    }
}
