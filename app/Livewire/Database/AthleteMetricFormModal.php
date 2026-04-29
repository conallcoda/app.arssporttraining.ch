<?php

namespace App\Livewire\Database;

use App\Data\Athlete\Metric\MetricEnum;
use App\Data\Athlete\Metric\MetricSubmissionData;
use App\Data\Athlete\Metric\ReadinessMetricData;
use App\Support\Readiness\ReadinessMetricService;
use App\Support\Readiness\ReadinessSurvey;
use Coda\Cms\Livewire\FormModal;
use Coda\FormKit\Form;
use Illuminate\View\View;
use Livewire\Attributes\Computed;

class AthleteMetricFormModal extends FormModal
{
    public string $metric = 'oneRepMax';

    public int $athleteId = 0;

    public bool $groupMode = false;

    public array $availableAthletes = [];

    public int $extremeOffset = ReadinessMetricData::DEFAULT_EXTREME_OFFSET;

    public function mount(
        string $name,
        string $title,
        ?string $formDataClass = null,
        string $submitLabel = 'Save',
        string $cancelLabel = 'Cancel',
        bool $flyout = true,
        string $maxWidth = 'max-w-sm',
        bool $showDelete = false,
        array $excludeFields = [],
    ): void {
        parent::mount($name, $title, $formDataClass, $submitLabel, $cancelLabel, $flyout, $maxWidth, $showDelete, $excludeFields);

        $this->athleteId = (int) request()->route('athleteId');
        $this->metric = request()->query('selectedTab', MetricEnum::OneRepMax->value);
    }

    public function getListeners(): array
    {
        return array_merge(parent::getListeners(), [
            'athlete-metric-tab-changed' => 'updateMetric',
        ]);
    }

    public function updateMetric(string $metric): void
    {
        $this->metric = $metric;

        if ($this->isReadinessMetric) {
            $this->initializeReadinessData();
        }
    }

    #[Computed]
    public function formConfig(): Form
    {
        $this->setMetricContext();

        return MetricSubmissionData::getForm();
    }

    protected function setMetricContext(): void
    {
        MetricSubmissionData::$formMetric = MetricEnum::from($this->metric);
        MetricSubmissionData::$formAthleteId = $this->athleteId;
    }

    public function open(array $data = [], ?string $title = null, ?string $focusField = null, ?int $focusIndex = null): void
    {
        $this->groupMode = (bool) ($data['_group_mode'] ?? false);
        $this->availableAthletes = $data['_available_athletes'] ?? [];

        unset($data['_group_mode'], $data['_available_athletes']);

        if (isset($data['user_id'])) {
            $this->athleteId = (int) $data['user_id'];
        }

        if (isset($data['metric'])) {
            $metricValue = $data['metric'] instanceof MetricEnum ? $data['metric']->value : (string) $data['metric'];
            $this->metric = $metricValue;
        } elseif ($queryTab = request()->query('selectedTab')) {
            $this->metric = $queryTab;
        }

        $this->setMetricContext();

        parent::open($data, $title, $focusField, $focusIndex);

        if ($this->isReadinessMetric) {
            $this->initializeReadinessData();
        }
    }

    public function updatedDataUserId($value): void
    {
        if ($value !== null) {
            $this->athleteId = (int) $value;
            $this->setMetricContext();
            if ($this->isReadinessMetric) {
                $this->hydrateReadinessBaseline();
            }
            unset($this->formConfig, $this->fieldsets);
        }
    }

    public function updatedDataRecordedAt(): void
    {
        if ($this->isReadinessMetric) {
            $this->hydrateReadinessBaseline();
        }
    }

    #[Computed]
    public function isReadinessMetric(): bool
    {
        return $this->metric === MetricEnum::Readiness->value;
    }

    #[Computed]
    public function readinessViewData(): array
    {
        return ReadinessSurvey::buildViewData($this->data['data'] ?? [], $this->extremeOffset);
    }

    protected function initializeReadinessData(array $incomingData = []): void
    {
        $existingData = $incomingData['data'] ?? $this->data['data'] ?? [];
        $this->data['data'] = array_merge(ReadinessSurvey::defaultState(), $existingData);
        $this->hydrateReadinessBaseline();
    }

    protected function hydrateReadinessBaseline(): void
    {
        $userId = (int) ($this->data['user_id'] ?? $this->athleteId);
        $recordedAt = $this->data['recorded_at'] ?? now()->format('Y-m-d');

        if ($userId <= 0 || ! blank($this->data['id'] ?? null)) {
            return;
        }

        $fallback = $this->data['data']['restingHeartRateBaseline'] ?? ReadinessSurvey::defaultState()['restingHeartRateBaseline'];
        $this->data['data']['restingHeartRateBaseline'] = app(ReadinessMetricService::class)->resolveBaseline(
            $userId,
            $recordedAt,
            $fallback,
        );
    }

    protected function validateReadinessData(): void
    {
        $this->validate([
            'data.data.restingHeartRate' => ['required', 'integer', 'min:30', 'max:200'],
            'data.data.hrv' => ['required', 'integer', 'min:0'],
        ], [], [
            'data.data.restingHeartRate' => 'resting heart rate',
            'data.data.hrv' => 'heart rate variability',
        ]);
    }

    public function submit(): void
    {
        if ($this->groupMode && empty($this->data['user_id'])) {
            $this->addError('data.user_id', __('Please select an athlete.'));

            return;
        }

        $this->setMetricContext();

        if ($this->isReadinessMetric) {
            $this->validateReadinessData();
        }

        parent::submit();
    }

    public function render(): View
    {
        return view('livewire.database.athlete-metric-form-modal');
    }
}
