<?php

namespace App\Livewire\Database;

use App\Data\Athlete\Metric\MetricEnum;
use App\Data\Athlete\Metric\MetricSubmissionData;
use App\Data\Athlete\Metric\ReadinessMetricData;
use App\Exceptions\DuplicateManualMetricSubmission;
use App\Support\AthleteMetrics\HeartRatePreviewBuilder;
use App\Support\Readiness\ReadinessMetricService;
use App\Support\Readiness\ReadinessSurvey;
use Coda\Cms\Livewire\FormModal;
use Coda\FormKit\Form;
use Illuminate\View\View;
use Livewire\Attributes\Computed;

class AthleteMetricFormModal extends FormModal
{
    protected const MODAL_MAX_WIDTH = 'max-w-[83.333%] overflow-x-hidden';

    protected const READINESS_MODAL_MAX_WIDTH = 'max-w-[96vw] xl:max-w-[1400px] overflow-x-hidden';

    public string $metric = 'oneRepMax';

    public int $athleteId = 0;

    public bool $groupMode = false;

    public array $availableAthletes = [];

    public int $extremeOffset = ReadinessMetricData::DEFAULT_EXTREME_OFFSET;

    public string $readinessModalTab = 'data';

    public function mount(
        string $name,
        string $title,
        ?string $formDataClass = null,
        string $submitLabel = 'Save',
        string $cancelLabel = 'Cancel',
        bool $flyout = true,
        string $maxWidth = self::MODAL_MAX_WIDTH,
        bool $showDelete = false,
        array $contextData = [],
        array $excludeFields = [],
        array $formTypes = [],
        bool $persistOnSubmit = false,
    ): void {
        parent::mount(
            name: $name,
            title: $title,
            formDataClass: $formDataClass,
            submitLabel: $submitLabel,
            cancelLabel: $cancelLabel,
            flyout: $flyout,
            maxWidth: $maxWidth,
            showDelete: $showDelete,
            contextData: $contextData,
            excludeFields: $excludeFields,
            formTypes: $formTypes,
            persistOnSubmit: $persistOnSubmit,
        );

        $this->athleteId = (int) request()->route('athleteId');
        $this->metric = $this->resolveMetricFromContext($name)
            ?? request()->query('selectedTab', MetricEnum::OneRepMax->value);
        $this->syncModalWidth();
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
        $this->syncModalWidth();

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

    public function open(
        array $data = [],
        ?string $title = null,
        ?string $focusField = null,
        ?int $focusIndex = null,
        array $formTypes = [],
        ?string $activeFormType = null,
        array $formTypeData = [],
    ): void {
        $this->groupMode = (bool) ($data['_group_mode'] ?? false);
        $this->availableAthletes = $data['_available_athletes'] ?? [];

        unset($data['_group_mode'], $data['_available_athletes']);

        if (isset($data['user_id'])) {
            $this->athleteId = (int) $data['user_id'];
        }

        if (isset($data['metric'])) {
            $metricValue = $data['metric'] instanceof MetricEnum ? $data['metric']->value : (string) $data['metric'];
            $this->metric = $metricValue;
        } elseif ($metricFromContext = $this->resolveMetricFromContext($this->name ?? null)) {
            $this->metric = $metricFromContext;
        } elseif ($queryTab = request()->query('selectedTab')) {
            $this->metric = $queryTab;
        }

        $this->setMetricContext();
        $this->syncModalWidth();
        $this->readinessModalTab = $this->isReadinessMetric && ! empty($data['id']) ? 'breakdown' : 'data';

        parent::open($data, $title, $focusField, $focusIndex, $formTypes, $activeFormType, $formTypeData);

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
    public function isHeartRateMetric(): bool
    {
        return $this->metric === MetricEnum::HeartRate->value;
    }

    #[Computed]
    public function readinessViewData(): array
    {
        return ReadinessSurvey::buildViewData($this->data['data'] ?? [], $this->extremeOffset);
    }

    #[Computed]
    public function heartRatePreviewSections(): array
    {
        $maxHeartRate = isset($this->data['data']['heartRate']) ? (int) $this->data['data']['heartRate'] : null;
        $anaerobicThreshold = isset($this->data['data']['anaerobicThreshold']) ? (int) $this->data['data']['anaerobicThreshold'] : null;

        return app(HeartRatePreviewBuilder::class)->buildSections($maxHeartRate, $anaerobicThreshold);
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
            'data.data.hrv' => ['nullable', 'integer', 'min:0'],
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

        try {
            parent::submit();
        } catch (DuplicateManualMetricSubmission $exception) {
            Flux::toast(text: __($exception->getMessage()), variant: 'warning');
        }
    }

    public function render(): View
    {
        return view('livewire.database.athlete-metric-form-modal');
    }

    #[Computed]
    public function showReadinessBreakdownTab(): bool
    {
        return $this->isReadinessMetric && ! empty($this->data['id']);
    }

    protected function resolveMetricFromContext(?string $name): ?string
    {
        if (! is_string($name) || $name === '') {
            return null;
        }

        return match (true) {
            str_contains($name, 'readiness') => MetricEnum::Readiness->value,
            str_contains($name, 'heart-rate') => MetricEnum::HeartRate->value,
            str_contains($name, 'one-rep-max') => MetricEnum::OneRepMax->value,
            default => null,
        };
    }

    protected function syncModalWidth(): void
    {
        $this->maxWidth = $this->isReadinessMetric
            ? self::READINESS_MODAL_MAX_WIDTH
            : self::MODAL_MAX_WIDTH;
    }
}
