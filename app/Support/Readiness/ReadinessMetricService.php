<?php

namespace App\Support\Readiness;

use App\Data\Athlete\Metric\MetricEnum;
use App\Models\Athlete\MetricSubmission;

class ReadinessMetricService
{
    public function findSubmissionForDate(int $userId, string $date): ?MetricSubmission
    {
        return MetricSubmission::query()
            ->forAthlete($userId)
            ->forMetric(MetricEnum::Readiness)
            ->manual()
            ->whereDate('recorded_at', $date)
            ->with('values')
            ->orderByDesc('updated_at')
            ->first();
    }

    /** @return array<string, int|null>|null */
    public function stateForDate(int $userId, string $date): ?array
    {
        $submission = $this->findSubmissionForDate($userId, $date);

        if ($submission === null) {
            return null;
        }

        return ReadinessSurvey::fromState($submission->values->pluck('value', 'field')->all())->toArray();
    }

    public function resolveBaseline(int $userId, string $date, ?int $fallback = null): ?int
    {
        $submissions = MetricSubmission::query()
            ->forAthlete($userId)
            ->forMetric(MetricEnum::Readiness)
            ->manual()
            ->whereDate('recorded_at', '<', $date)
            ->with('values')
            ->orderByDesc('recorded_at')
            ->limit(7)
            ->get();

        $average = (int) round($submissions
            ->map(function (MetricSubmission $submission): ?int {
                $metric = ReadinessSurvey::fromState($submission->values->pluck('value', 'field')->all());

                return $metric->restingHeartRate;
            })
            ->filter(static fn (?int $value): bool => $value !== null)
            ->avg() ?? 0);

        if ($average > 0) {
            return $average;
        }

        return $fallback;
    }

    /** @return array{score: float|null, label: string|null, color: string|null, state: array<string, int|null>|null} */
    public function presentationForDate(int $userId, string $date): array
    {
        $state = $this->stateForDate($userId, $date);

        if ($state === null) {
            return [
                'score' => null,
                'label' => null,
                'color' => null,
                'state' => null,
            ];
        }

        $view = ReadinessSurvey::buildViewData($state);

        return [
            'score' => $view['readinessScore'],
            'label' => $view['trafficLightLabel'],
            'color' => $view['trafficLightColor'],
            'state' => $state,
        ];
    }
}
