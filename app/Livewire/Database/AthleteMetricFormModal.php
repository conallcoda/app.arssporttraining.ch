<?php

namespace App\Livewire\Database;

use App\Data\Athlete\Metric\MetricEnum;
use App\Data\Athlete\Metric\MetricSubmissionData;
use App\Data\Athlete\Metric\ReadinessMetricData;
use App\Data\Exercise\Strategies\HeartRate\HeartRateZoneCellColors;
use App\Support\Readiness\ReadinessMetricService;
use App\Support\Readiness\ReadinessSurvey;
use App\Training\Reference\BikingZoneTable;
use App\Training\Reference\JoggingZoneTable;
use Coda\Cms\Livewire\FormModal;
use Coda\FormKit\Form;
use Illuminate\View\View;
use Livewire\Attributes\Computed;

class AthleteMetricFormModal extends FormModal
{
    protected const MODAL_MAX_WIDTH = 'max-w-[83.333%] overflow-x-hidden';

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
        );

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
        $this->readinessModalTab = $this->isReadinessMetric && ! empty($data['id']) ? 'breakdown' : 'data';

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

        return [
            $this->buildHeartRatePreviewSection(
                title: 'Bike',
                tableClass: BikingZoneTable::class,
                maxHeartRate: $maxHeartRate,
                anaerobicThreshold: $anaerobicThreshold,
                zoneTwoUpperPercent: $anaerobicThreshold,
            ),
            $this->buildHeartRatePreviewSection(
                title: 'Jogging',
                tableClass: JoggingZoneTable::class,
                maxHeartRate: $maxHeartRate,
                anaerobicThreshold: $anaerobicThreshold,
                zoneTwoUpperPercent: $anaerobicThreshold !== null ? $anaerobicThreshold + 5 : null,
            ),
        ];
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

    #[Computed]
    public function showReadinessBreakdownTab(): bool
    {
        return $this->isReadinessMetric && ! empty($this->data['id']);
    }

    /** @return array{title: string, maxHeartRate: ?int, anaerobicThreshold: ?int, rows: array<int, array{name: string, bpm: string, percent: string, classes: string}>} */
    protected function buildHeartRatePreviewSection(
        string $title,
        string $tableClass,
        ?int $maxHeartRate,
        ?int $anaerobicThreshold,
        ?int $zoneTwoUpperPercent,
    ): array {
        $percentTable = $tableClass::getTable();
        $rows = [];

        foreach ($this->heartRateZoneRows() as $zone => $meta) {
            [$lowerPercent, $upperPercent] = $percentTable[$zone];
            $range = ($maxHeartRate !== null && $anaerobicThreshold !== null)
                ? $tableClass::getRange($zone, $maxHeartRate, $anaerobicThreshold)
                : null;

            $rows[] = [
                'name' => $meta['name'],
                'bpm' => $range ? "{$range['lower']} - {$range['upper']} bpm" : '—',
                'percent' => match ($zone) {
                    2 => $zoneTwoUpperPercent !== null ? "{$lowerPercent}% - {$zoneTwoUpperPercent}%" : '—',
                    default => $upperPercent !== null ? "{$lowerPercent}% - {$upperPercent}%" : '—',
                },
                'classes' => $meta['classes'],
            ];
        }

        return [
            'title' => $title,
            'maxHeartRate' => $maxHeartRate,
            'anaerobicThreshold' => $anaerobicThreshold,
            'rows' => $rows,
        ];
    }

    /** @return array<int, array{name: string, classes: string}> */
    protected function heartRateZoneRows(): array
    {
        $zoneColors = new HeartRateZoneCellColors;

        return [
            0 => ['name' => 'Reg', 'classes' => $zoneColors->cellColor('heartRateZone', '0') ?? ''],
            1 => ['name' => 'Zone 1', 'classes' => $zoneColors->cellColor('heartRateZone', '1') ?? ''],
            2 => ['name' => 'Zone 2', 'classes' => $zoneColors->cellColor('heartRateZone', '2') ?? ''],
            3 => ['name' => 'Zone 3', 'classes' => $zoneColors->cellColor('heartRateZone', '3') ?? ''],
            4 => ['name' => 'Zone MAX', 'classes' => $zoneColors->cellColor('heartRateZone', '4') ?? ''],
        ];
    }
}
