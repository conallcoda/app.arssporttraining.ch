<?php

namespace App\Support\AthleteMetrics;

use App\Data\Athlete\Metric\AbstractMetric;
use App\Data\Athlete\Metric\MetricEnum;
use App\Data\Athlete\Metric\Metrics\HeartRateMetric;
use App\Data\Athlete\Metric\Metrics\OneRepMaxMetric;
use App\Data\Athlete\Metric\Metrics\ReadinessMetric;
use App\Data\Athlete\Metric\MetricSubmissionData;
use App\Models\Athlete\MetricSubmission;

class AthleteMetricSnapshotService
{
    public function latestCurrentSubmission(
        int $athleteId,
        MetricEnum $metric,
        ?string $cutoffDate = null,
    ): ?MetricSubmission {
        $cutoffDate ??= now()->format('Y-m-d');

        $query = MetricSubmission::query()
            ->forAthlete($athleteId)
            ->forMetric($metric)
            ->manual()
            ->where('recorded_at', '<=', $cutoffDate)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->with('values');

        if ($metric !== MetricEnum::Readiness) {
            return $query->first();
        }

        return $query->get()->first(function (MetricSubmission $submission): bool {
            $fieldValues = $submission->values->pluck('value', 'field')->all();
            $metric = ReadinessMetric::from($fieldValues);

            return $this->readinessCurrentLabel($fieldValues, $metric) !== null;
        });
    }

    /**
     * @return array{
     *     metric: MetricEnum,
     *     submission: ?MetricSubmission,
     *     instance: ?AbstractMetric,
     *     fieldValues: array<string, mixed>,
     *     summary: string,
     *     recordedAt: ?string,
     *     data: ?array<string, mixed>,
     *     isAvailable: bool
     * }
     */
    public function currentSnapshot(
        int $athleteId,
        MetricEnum $metric,
        ?string $cutoffDate = null,
    ): array {
        $submission = $this->latestCurrentSubmission($athleteId, $metric, $cutoffDate);

        if (! $submission) {
            return [
                'metric' => $metric,
                'submission' => null,
                'instance' => null,
                'fieldValues' => [],
                'summary' => 'N/A',
                'recordedAt' => null,
                'data' => null,
                'isAvailable' => false,
            ];
        }

        $fieldValues = $submission->values->pluck('value', 'field')->all();
        $instance = $metric->metricClass()::from($fieldValues);
        $label = $this->currentMetricDisplayLabel($metric, $fieldValues, $instance);

        return [
            'metric' => $metric,
            'submission' => $submission,
            'instance' => $instance,
            'fieldValues' => $fieldValues,
            'summary' => $label ?? 'N/A',
            'recordedAt' => $submission->recorded_at?->format('d.m.Y'),
            'data' => MetricSubmissionData::fromModel($submission)->toArray(),
            'isAvailable' => $label !== null,
        ];
    }

    protected function currentMetricDisplayLabel(
        MetricEnum $metric,
        array $fieldValues,
        AbstractMetric $metricInstance,
    ): ?string {
        return match ($metric) {
            MetricEnum::OneRepMax => $this->oneRepMaxCurrentLabel($metricInstance),
            MetricEnum::HeartRate => $this->heartRateCurrentLabel($fieldValues),
            MetricEnum::Readiness => $this->readinessCurrentLabel($fieldValues, $metricInstance),
        };
    }

    protected function oneRepMaxCurrentLabel(AbstractMetric $metricInstance): ?string
    {
        if (! $metricInstance instanceof OneRepMaxMetric || $metricInstance->measuredWeight === null) {
            return null;
        }

        return $metricInstance->estimatedLabel().'kg';
    }

    protected function heartRateCurrentLabel(array $fieldValues): ?string
    {
        $metric = HeartRateMetric::from($fieldValues);

        if ($metric->heartRate === null) {
            return null;
        }

        $label = $metric->heartRate.' HR';

        if ($metric->anaerobicThreshold !== null) {
            $label .= ' - '.$metric->anaerobicThreshold.'% IAT';
        }

        return $label;
    }

    protected function readinessCurrentLabel(array $fieldValues, AbstractMetric $metricInstance): ?string
    {
        $score = isset($fieldValues['readinessScore'])
            ? (float) $fieldValues['readinessScore']
            : ($metricInstance instanceof ReadinessMetric ? $metricInstance->data()->readinessScore() : null);

        return $score !== null ? number_format($score, 1) : null;
    }
}
