<?php

namespace App\Data\Athlete\Metric;

use App\Data\Athlete\Metric\Metrics\OneRepMaxMetric;
use App\Exceptions\DuplicateManualMetricSubmission;
use App\Models\Athlete\MetricSubmission;
use App\Models\Users\User;
use App\Training\TrainingSessionRebuildService;
use Coda\Cms\Data\AbstractData;
use Coda\FormKit\Concerns\InteractsWithForms;
use Coda\FormKit\Contracts\HasForms;
use Coda\FormKit\Fields;
use Coda\FormKit\Form;

class MetricSubmissionData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public static ?MetricEnum $formMetric = null;

    public static ?int $formAthleteId = null;

    public ?string $metricLabel = null;

    public function __construct(
        public ?int $id = null,
        public ?int $user_id = null,
        public MetricEnum $metric = MetricEnum::OneRepMax,
        public ?int $recorded_by = null,
        public ?string $recorded_at = null,
        public AbstractMetric $data = new OneRepMaxMetric,
    ) {
        $this->metricLabel = $this->metric->label();
    }

    public static function fromModel(MetricSubmission $submission): self
    {
        $metricClass = $submission->metric->metricClass();

        $fieldValues = $submission->relationLoaded('values')
            ? $submission->values->pluck('value', 'field')->all()
            : [];

        return new self(
            id: $submission->id,
            user_id: $submission->user_id,
            metric: $submission->metric,
            recorded_by: $submission->recorded_by,
            recorded_at: $submission->recorded_at?->format('Y-m-d'),
            data: $metricClass::from($fieldValues),
        );
    }

    public function persist(): MetricSubmission
    {
        $existingSubmission = $this->id !== null
            ? MetricSubmission::query()->find($this->id)
            : null;

        if ($this->metric === MetricEnum::OneRepMax) {
            $editingProjectedSubmission = $existingSubmission !== null
                && $existingSubmission->owner_type !== null
                && $existingSubmission->owner_type !== User::class;

            if ($editingProjectedSubmission) {
                $this->id = null;
            }

            if ($this->data instanceof OneRepMaxMetric) {
                $this->data->goalPercent = null;
            }

            if ($this->user_id !== null && $this->recorded_at !== null) {
                $existingManualSubmission = MetricSubmission::query()
                    ->forAthlete($this->user_id)
                    ->forMetric(MetricEnum::OneRepMax)
                    ->manual()
                    ->whereDate('recorded_at', $this->recorded_at)
                    ->when($this->id !== null, fn ($query) => $query->whereKeyNot($this->id))
                    ->orderByDesc('id')
                    ->first();

                if ($existingManualSubmission !== null) {
                    throw new DuplicateManualMetricSubmission;
                }
            }
        }

        if (
            $this->id === null
            && $this->metric === MetricEnum::Readiness
            && $this->user_id !== null
            && $this->recorded_at !== null
        ) {
            $this->id = MetricSubmission::query()
                ->forAthlete($this->user_id)
                ->forMetric(MetricEnum::Readiness)
                ->manual()
                ->whereDate('recorded_at', $this->recorded_at)
                ->value('id');
        }

        $submission = MetricSubmission::updateOrCreate(
            ['id' => $this->id],
            [
                'user_id' => $this->user_id,
                'metric' => $this->metric,
                'recorded_by' => $this->recorded_by ?? auth()->id(),
                'recorded_at' => $this->recorded_at,
                'owner_type' => User::class,
                'owner_id' => $this->recorded_by ?? auth()->id(),
            ]
        );

        $this->id = $submission->id;

        $submission->values()->delete();

        $fieldValues = array_filter($this->data->toArray(), fn ($v) => $v !== null);
        $derivedValues = $this->data::derivedValues($fieldValues);

        $allValues = array_merge($fieldValues, $derivedValues);

        foreach ($allValues as $field => $value) {
            $submission->values()->create([
                'field' => $field,
                'value' => (string) $value,
            ]);
        }

        $this->rebuildAffectedTrainingSlots($submission);

        return $submission;
    }

    private function rebuildAffectedTrainingSlots(MetricSubmission $submission): void
    {
        if (! in_array($submission->metric, [MetricEnum::OneRepMax, MetricEnum::HeartRate], true)) {
            return;
        }

        if ($submission->user_id === null) {
            return;
        }

        app(TrainingSessionRebuildService::class)->rebuildOpenSlotsForAthlete(
            (int) $submission->user_id,
            $submission->recorded_at?->format('Y-m-d'),
        );
    }

    public static function getForm(): Form
    {
        $metric = static::$formMetric ?? MetricEnum::OneRepMax;
        $metricClass = $metric->metricClass();

        return Form::make()
            ->fieldset('Submission', [
                Fields\Date::make('recorded_at')
                    ->label('Date')
                    ->required()
                    ->default(now()->format('Y-m-d')),
            ])
            ->fieldset($metricClass::getName(), $metricClass::fields(), 'data.data');
    }
}
