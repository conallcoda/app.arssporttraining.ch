<?php

namespace App\Livewire\Database;

use App\Data\Athlete\Metric\MetricEnum;
use App\Data\Athlete\Metric\MetricSubmissionData;
use Coda\Cms\Form\Form;
use Coda\Cms\Livewire\FormModal;
use Livewire\Attributes\Computed;

class AthleteMetricFormModal extends FormModal
{
    public string $metric = 'oneRepMax';

    public int $athleteId = 0;

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
    }

    public function submit(): void
    {
        $this->setMetricContext();

        parent::submit();
    }
}
