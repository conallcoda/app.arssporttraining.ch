<?php

namespace App\Livewire\Database;

use App\Data\Athlete\Metric\MetricEnum;
use App\Data\Athlete\Metric\MetricSubmissionData;
use App\Display\DisplayFields\MetricSummary;
use App\Exceptions\DuplicateManualMetricSubmission;
use App\Models\Athlete\MetricSubmission;
use Coda\Cms\Data\AbstractData;
use Coda\Cms\Display\DisplayFields\Date;
use Coda\Cms\Display\IndexTab;
use Coda\Cms\Display\Table;
use Coda\Cms\Livewire\AbstractModelList;
use Coda\FormKit\Action;
use Coda\FormKit\Form;
use Flux\Flux;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AthleteMetricList extends AbstractModelList
{
    public int $athleteId = 0;

    public ?string $forcedMetric = null;

    public bool $showTabs = true;

    public function mount(...$routeParameters): void
    {
        $parameters = $this->normalizeMountParameters($routeParameters);

        $athleteId = (int) ($parameters['athleteId'] ?? $parameters[0] ?? 0);
        $forcedMetric = $parameters['forcedMetric'] ?? $parameters[1] ?? null;
        $showTabs = (bool) ($parameters['showTabs'] ?? $parameters[2] ?? true);
        $prefixUrl = (bool) ($parameters['prefixUrl'] ?? $parameters[3] ?? false);

        $this->athleteId = $athleteId ?: (int) request()->route('athleteId');
        $this->forcedMetric = MetricEnum::tryFrom((string) $forcedMetric)?->value;
        $this->showTabs = $showTabs;
        $this->prefixUrl = $prefixUrl;

        if ($this->forcedMetric !== null) {
            $this->selectedTab = $this->forcedMetric;
        }

        $selectedTab = request()->query('selectedTab', request()->query($this->urlPrefix().'selectedTab'));
        if ($this->forcedMetric === null && is_string($selectedTab) && $selectedTab !== '') {
            $this->selectedTab = $selectedTab;
        }

        parent::mount(...$parameters);
    }

    protected function normalizeMountParameters(array $routeParameters): array
    {
        if (isset($routeParameters[0]) && is_array($routeParameters[0])) {
            $routeParameters = array_merge($routeParameters[0], $routeParameters);
            unset($routeParameters[0]);
        }

        foreach ([
            'athlete-id' => 'athleteId',
            'athlete_id' => 'athleteId',
            'forced-metric' => 'forcedMetric',
            'forced_metric' => 'forcedMetric',
            'show-tabs' => 'showTabs',
            'show_tabs' => 'showTabs',
            'prefix-url' => 'prefixUrl',
            'prefix_url' => 'prefixUrl',
        ] as $from => $to) {
            if (array_key_exists($from, $routeParameters) && ! array_key_exists($to, $routeParameters)) {
                $routeParameters[$to] = $routeParameters[$from];
            }
        }

        return $routeParameters;
    }

    protected function getEntityName(): string
    {
        return 'Metric';
    }

    protected function urlPrefix(): string
    {
        if ($this->forcedMetric !== null) {
            return Str::of($this->forcedMetric)->kebab()->append('_')->toString();
        }

        return 'am_';
    }

    protected function getEntitySlug(): string
    {
        if ($this->forcedMetric !== null) {
            return 'athlete-metric-'.Str::of($this->forcedMetric)->kebab()->toString();
        }

        return parent::getEntitySlug();
    }

    protected function getDataClass(): string
    {
        return MetricSubmissionData::class;
    }

    protected function getTabs(): array
    {
        if (! $this->showTabs || $this->forcedMetric !== null) {
            return [];
        }

        return collect(MetricEnum::cases())
            ->map(fn (MetricEnum $case) => IndexTab::make($case->value, $case->label())
                ->query(fn (Builder $query) => $query->where('metric', $case->value))
            )
            ->all();
    }

    protected function getDefaultTabKey(): ?string
    {
        if ($this->forcedMetric !== null) {
            return $this->forcedMetric;
        }

        return MetricEnum::OneRepMax->value;
    }

    public function updatedSelectedTab(): void
    {
        parent::updatedSelectedTab();
        unset($this->actions, $this->headerActions);
        $this->dispatch('athlete-metric-tab-changed', metric: $this->selectedTab);
    }

    protected function getAddAction(): ?Action
    {
        return parent::getAddAction()
            ?->formComponent('database.athlete-metric-form-modal')
            ->formModal($this->getDataClass(), __('Add Metric')." ({$this->metricLabel()})");
    }

    protected function selectedMetric(): MetricEnum
    {
        return MetricEnum::tryFrom($this->forcedMetric ?? $this->selectedTab ?? '')
            ?? MetricEnum::OneRepMax;
    }

    protected function getBaseQuery(): Builder
    {
        $query = MetricSubmission::query()
            ->where('user_id', $this->athleteId)
            ->with(['recordedBy', 'values']);

        if ($this->forcedMetric !== null) {
            $query->where('metric', $this->selectedMetric());
        }

        return $query;
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

    protected function metricLabel(): string
    {
        return $this->selectedMetric()->label();
    }

    protected function getFormModalMaxWidth(): string
    {
        return 'max-w-[83.333%] overflow-x-hidden';
    }

    public function handleFormSubmitted(array $data): void
    {
        try {
            parent::handleFormSubmitted($data);
        } catch (DuplicateManualMetricSubmission $exception) {
            Flux::toast(text: __($exception->getMessage()), variant: 'warning');
        }
    }

    public function startEdit(int $id, ?string $focusField = null, ?int $focusIndex = null): void
    {
        $this->edit = $id;

        $model = $this->getBaseQuery()->findOrFail($id);
        $data = $this->dataFromModel($model)->toArray();
        $metricLabel = $model->metric->label();

        $this->dispatch(
            "open-{$this->editModalName}",
            data: $data,
            title: __('Edit Metric')." ({$metricLabel})",
            focusField: $focusField,
            focusIndex: $focusIndex,
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

    protected function emit(): void
    {
        $this->dispatch('athlete-metric-snapshots-changed', athleteId: $this->athleteId, metric: $this->selectedMetric()->value);
    }
}
