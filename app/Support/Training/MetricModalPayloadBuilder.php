<?php

namespace App\Support\Training;

use App\Data\Athlete\Metric\MetricEnum;
use App\Data\Athlete\Metric\MetricSubmissionData;
use App\Models\Athlete\MetricSubmission;

class MetricModalPayloadBuilder
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{data: array<string, mixed>, title: string}
     */
    public function fromExistingData(array $data, MetricEnum|string $metric): array
    {
        $metricEnum = $this->normalizeMetric($metric);

        return [
            'data' => array_merge($data, ['metric' => $metricEnum->value]),
            'title' => $this->title('edit', $metricEnum),
        ];
    }

    /**
     * @return array{data: array<string, mixed>, title: string}
     */
    public function fromSubmission(MetricSubmission $submission, MetricEnum|string $metric): array
    {
        return $this->fromExistingData(
            MetricSubmissionData::fromModel($submission)->toArray(),
            $metric,
        );
    }

    /**
     * @return array{data: array<string, mixed>, title: string}
     */
    public function forCreation(MetricEnum|string $metric, string $recordedAt, ?int $userId): array
    {
        $metricEnum = $this->normalizeMetric($metric);

        return [
            'data' => [
                'metric' => $metricEnum->value,
                'recorded_at' => $recordedAt,
                'user_id' => $userId,
            ],
            'title' => $this->title('add', $metricEnum),
        ];
    }

    /**
     * @param  list<array{id:int,name:string}>  $availableAthletes
     * @return array{data: array<string, mixed>, title: string}
     */
    public function forGroupCreation(MetricEnum|string $metric, string $recordedAt, array $availableAthletes): array
    {
        $payload = $this->forCreation($metric, $recordedAt, null);
        $payload['data']['_group_mode'] = true;
        $payload['data']['_available_athletes'] = $availableAthletes;

        return $payload;
    }

    private function normalizeMetric(MetricEnum|string $metric): MetricEnum
    {
        return $metric instanceof MetricEnum ? $metric : MetricEnum::from($metric);
    }

    private function title(string $mode, MetricEnum $metric): string
    {
        $prefix = $mode === 'edit' ? __('Edit Metric') : __('Add Metric');

        return "{$prefix} ({$metric->label()})";
    }
}
