<?php

namespace App\Livewire\Database;

use App\Data\Athlete\Metric\MetricEnum;
use App\Data\Athlete\Metric\MetricSubmissionData;
use App\Display\DisplayFields\MetricSummary;
use App\Models\Athlete\MetricSubmission;
use Coda\Cms\Data\AbstractData;
use Coda\Cms\Display\DisplayFields\Date;
use Coda\Cms\Display\IndexTab;
use Coda\Cms\Display\Table;
use Coda\Cms\Form\Action;
use Coda\Cms\Form\Form;
use Coda\Cms\Livewire\AbstractModelList;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AthleteMetricList extends AbstractModelList
{
    public int $athleteId;

    public function mount(int $athleteId = 0): void
    {
        $this->athleteId = $athleteId ?: (int) request()->route('athleteId');
        parent::mount();
    }

    protected function urlPrefix(): string
    {
        return 'am_';
    }

    protected function getDataClass(): string
    {
        return MetricSubmissionData::class;
    }

    protected function getTabs(): array
    {
        return collect(MetricEnum::cases())
            ->map(fn (MetricEnum $case) => IndexTab::make($case->value, $case->label())
                ->query(fn (Builder $query) => $query->where('metric', $case->value))
            )
            ->all();
    }

    protected function getDefaultTabKey(): ?string
    {
        return MetricEnum::OneRepMax->value;
    }

    public function updatedSelectedTab(): void
    {
        parent::updatedSelectedTab();
        $this->dispatch('athlete-metric-tab-changed', metric: $this->selectedTab);
    }

    protected function getAddAction(): ?Action
    {
        return parent::getAddAction()?->formComponent('database.athlete-metric-form-modal');
    }

    protected function selectedMetric(): MetricEnum
    {
        return MetricEnum::tryFrom($this->selectedTab ?? '') ?? MetricEnum::OneRepMax;
    }

    protected function getBaseQuery(): Builder
    {
        return MetricSubmission::query()
            ->where('user_id', $this->athleteId)
            ->with(['recordedBy', 'values']);
    }

    protected function dataFromModel(Model $model): AbstractData
    {
        return MetricSubmissionData::fromModel($model);
    }

    protected function getFormDefinition(): Form|array
    {
        MetricSubmissionData::$formMetric = $this->selectedMetric();
        MetricSubmissionData::$formAthleteId = $this->athleteId;

        return MetricSubmissionData::getForm();
    }

    protected function createDataFromForm(array $formData): AbstractData
    {
        $metric = $this->selectedMetric();
        $metricClass = $metric->metricClass();

        return new MetricSubmissionData(
            id: $formData['id'] ?? null,
            user_id: $this->athleteId,
            metric: $metric,
            recorded_by: auth()->id(),
            recorded_at: $formData['recorded_at'] ?? null,
            data: $metricClass::from($formData['data'] ?? []),
        );
    }

    protected function getTable(): Table
    {
        return Table::make()
            ->columns([
                Date::make('recorded_at')
                    ->label('Date')
                    ->modal(),
                MetricSummary::make('data')
                    ->label('Metrics'),
            ])
            ->defaultSort('recorded_at', 'desc')
            ->sortable(['recorded_at']);
    }
}
