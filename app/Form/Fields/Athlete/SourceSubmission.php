<?php

namespace App\Form\Fields\Athlete;

use App\Data\Athlete\Metric\MetricEnum;
use App\Models\Athlete\MetricSubmission;
use Coda\Cms\Form\Fields\SelectEntity;

class SourceSubmission extends SelectEntity
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Source 1RM';
        $this->required = true;
        $this->validationRules = 'required|integer|exists:user_metric_submissions,id';
    }

    public function withOptions(int $athleteId): static
    {
        $this->optionLoader = function () use ($athleteId) {
            $submissions = MetricSubmission::query()
                ->forAthlete($athleteId)
                ->forMetric(MetricEnum::OneRepMax)
                ->with('values')
                ->orderByDesc('recorded_at')
                ->get();

            $first = true;
            $options = [];

            foreach ($submissions as $submission) {
                $estimated1RM = $submission->getFieldValue('estimated1RM') ?? '?';
                $date = $submission->recorded_at->format('Y-m-d');
                $options[$submission->id] = "{$date} — {$estimated1RM}kg";

                if ($first) {
                    $this->default = $submission->id;
                    $first = false;
                }
            }

            return $options;
        };

        return $this;
    }
}
