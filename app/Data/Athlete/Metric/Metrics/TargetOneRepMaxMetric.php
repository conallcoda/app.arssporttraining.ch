<?php

namespace App\Data\Athlete\Metric\Metrics;

use App\Data\Athlete\Metric\AbstractMetric;
use App\Form\Fields\Athlete\GoalPercent;
use App\Form\Fields\Athlete\SourceSubmission;
use App\Models\Athlete\MetricSubmission;
use App\Training\Reference\OneRepMaxConversion;

class TargetOneRepMaxMetric extends AbstractMetric
{
    public function __construct(
        public ?int $sourceSubmissionId = null,
        public ?float $goalPercent = 10,
    ) {}

    public static function fields(): array
    {
        $field = SourceSubmission::make('sourceSubmissionId');

        $athleteId = static::$formAthleteId;
        if ($athleteId) {
            $field->withOptions($athleteId);
        }

        return [
            $field,
            GoalPercent::make('goalPercent'),
        ];
    }

    public static ?int $formAthleteId = null;

    /** @return array<string, string> */
    public static function derivedValues(array $fieldValues): array
    {
        $sourceId = (int) ($fieldValues['sourceSubmissionId'] ?? 0);
        $goalPercent = (float) ($fieldValues['goalPercent'] ?? 10);

        if (! $sourceId) {
            return ['target1RM' => '0'];
        }

        $source = MetricSubmission::find($sourceId);
        $estimated1RM = (float) ($source?->getFieldValue('estimated1RM') ?? 0);

        return [
            'target1RM' => (string) OneRepMaxConversion::targetOneRepMax($estimated1RM, $goalPercent),
        ];
    }
}
