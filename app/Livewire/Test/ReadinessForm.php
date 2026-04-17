<?php

namespace App\Livewire\Test;

use App\Data\Athlete\Metric\ReadinessMetricData;
use Coda\Cms\Livewire\CmsPage;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ReadinessForm extends Component
{
    public int $sleepMinutes = 450;

    public int $sleepQuality = 3;

    public int $altitudeMeters = 2000;

    public int $condition = 3;

    public int $mood = 3;

    public int $motivation = 3;

    public int $soreness = 5;

    public int $energy = 3;

    public int $restingHeartRate = 60;

    public int $restingHeartRateBaseline = 60;

    public ?int $hrv = null;

    public int $extremeOffset = -5;

    public function adjustScore(int|float $score): int|float
    {
        return $score <= 1 ? $this->extremeOffset : $score;
    }

    #[Computed]
    public function data(): ReadinessMetricData
    {
        return new ReadinessMetricData(
            sleepMinutes: $this->sleepMinutes,
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
        return ReadinessMetricData::sleepDurationScore($this->sleepMinutes);
    }

    #[Computed]
    public function sleepDurationFormatted(): string
    {
        return match ($this->sleepMinutes) {
            360 => '<6:00',
            510 => '>8:30',
            default => ReadinessMetricData::formatSleepDuration($this->sleepMinutes),
        };
    }

    #[Computed]
    public function altitudeFormatted(): string
    {
        return match ($this->altitudeMeters) {
            1500 => '<1500',
            3000 => '>3000',
            default => (string) $this->altitudeMeters,
        };
    }

    #[Computed]
    public function sleepDurationLabel(): ?string
    {
        return ReadinessMetricData::sleepDurationLabel($this->sleepMinutes);
    }

    #[Computed]
    public function altitudeScore(): ?int
    {
        return ReadinessMetricData::altitudeScore($this->altitudeMeters);
    }

    #[Computed]
    public function altitudeLabel(): ?string
    {
        return ReadinessMetricData::altitudeLabel($this->altitudeMeters);
    }

    #[Computed]
    public function rhrScore(): ?int
    {
        return ReadinessMetricData::rhrScore($this->restingHeartRate, $this->restingHeartRateBaseline);
    }

    #[Computed]
    public function sleepQualityWeighted(): float
    {
        return $this->adjustScore($this->sleepQuality) * 0.40;
    }

    #[Computed]
    public function sleepDurationWeighted(): ?float
    {
        $score = ReadinessMetricData::sleepDurationScore($this->sleepMinutes);

        return $score === null ? null : $this->adjustScore($score) * 0.40;
    }

    #[Computed]
    public function altitudeWeighted(): ?float
    {
        $score = ReadinessMetricData::altitudeScore($this->altitudeMeters);

        return $score === null ? null : $this->adjustScore($score) * 0.20;
    }

    #[Computed]
    public function sleepScore(): ?float
    {
        $d = $this->sleepDurationWeighted;
        $a = $this->altitudeWeighted;

        if ($d === null || $a === null) {
            return null;
        }

        return $this->sleepQualityWeighted + $d + $a;
    }

    #[Computed]
    public function rhrDelta(): int
    {
        return $this->restingHeartRate - $this->restingHeartRateBaseline;
    }

    #[Computed]
    public function readinessComponentsSum(): ?float
    {
        $sleep = $this->sleepScore;
        $rhr = $this->rhrScore;

        if ($sleep === null || $rhr === null) {
            return null;
        }

        return $sleep
            + $this->adjustScore($this->condition)
            + $this->adjustScore($this->mood)
            + $this->adjustScore($this->motivation)
            + $this->adjustScore($this->soreness)
            + $this->adjustScore($this->energy)
            + $this->adjustScore($rhr);
    }

    #[Computed]
    public function readinessScore(): ?float
    {
        $sum = $this->readinessComponentsSum;

        return $sum === null ? null : $sum / 7;
    }

    #[Computed]
    public function trafficLight(): ?string
    {
        $score = $this->readinessScore;

        if ($score === null) {
            return null;
        }

        return match (true) {
            $score >= 4.0 => 'ready',
            $score >= 3.0 => 'train_smart',
            $score >= 2.0 => 'recovery',
            default => 'rest',
        };
    }

    public function render()
    {
        return view('livewire.test.readiness-form')
            ->layout(CmsPage::layout())
            ->title(CmsPage::buildTitle(__('Readiness')));
    }
}
