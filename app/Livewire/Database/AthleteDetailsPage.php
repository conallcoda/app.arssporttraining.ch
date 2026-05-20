<?php

namespace App\Livewire\Database;

use App\Data\Athlete\Metric\MetricEnum;
use App\Data\Athlete\Metric\Metrics\HeartRateMetric;
use App\Data\Athlete\Metric\Metrics\OneRepMaxMetric;
use App\Data\Training\Blocks\CategoryBlockType;
use App\Models\Users\AccountSetupStatus;
use App\Models\Users\User;
use App\Models\Users\UserTypeEnum;
use App\Notifications\AccountSetupNotification;
use App\Support\AthleteMetrics\AthleteMetricSnapshotService;
use App\Support\AthleteMetrics\HeartRatePreviewBuilder;
use App\Support\AthleteMetrics\OneRepMaxExamplePreviewBuilder;
use App\Support\Readiness\ReadinessSurvey;
use Coda\Cms\Form\Forms\ChangePasswordForm;
use Coda\Cms\Livewire\AbstractModelDetailsPage;
use Coda\FormKit\Fields\Number;
use Flux\Flux;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Attributes\Computed;

class AthleteDetailsPage extends AbstractModelDetailsPage
{
    public array $oneRepMaxPreview = [];

    public function mount(int $record): void
    {
        parent::mount($record);
        $this->initializeOneRepMaxPreview();
        $this->initializeTabState('general');

        if (! in_array($this->activeTab, ['general', 'readiness', 'heart_rate', 'one_rep_max'], true)) {
            $this->activeTab = 'general';
        }
    }

    public function getListeners(): array
    {
        return [
            'athlete-change-password.submitted' => 'handleChangePasswordSubmitted',
            'athlete-metric-snapshots-changed' => 'refreshMetricSnapshots',
        ];
    }

    public function sendSetupAccountEmail(): void
    {
        $user = $this->resolveAthlete();

        if (! $user->hasSetupEmail()) {
            Flux::toast(text: "Add an email address for {$user->name} before sending a setup email.", variant: 'danger');

            return;
        }

        if ($user->accountSetupStatus() === AccountSetupStatus::Active) {
            Flux::toast(text: "{$user->name} is already active. Use the normal password reset flow instead.", variant: 'danger');

            return;
        }

        $token = $user->issueAccountSetupToken();
        $user->notify(new AccountSetupNotification($token));

        $this->loadRecord();

        Flux::toast(text: "Setup email sent to {$user->email}", variant: 'success');
    }

    public function handleChangePasswordSubmitted(array $data): void
    {
        $user = $this->resolveAthlete();
        $user->update(['password' => Hash::make((string) ($data['password'] ?? ''))]);

        $this->loadRecord();

        Flux::toast(text: "Password changed for {$user->name}", variant: 'success');
    }

    public function changePasswordModalName(): string
    {
        return 'athlete-change-password';
    }

    public function changePasswordFormClass(): string
    {
        return ChangePasswordForm::class;
    }

    public function refreshMetricSnapshots(): void
    {
        unset(
            $this->readinessSnapshot,
            $this->readinessViewData,
            $this->heartRateSnapshot,
            $this->heartRatePreviewSections,
            $this->oneRepMaxSnapshot,
            $this->oneRepMaxPreviewGrid,
        );
    }

    public function updatedOneRepMaxPreviewTargetGoal(mixed $value): void
    {
        $validator = Validator::make(
            ['targetGoal' => $value],
            ['targetGoal' => $this->oneRepMaxPreviewGoalRules()],
            ['required' => 'This field is required.'],
            ['targetGoal' => strtolower($this->resolveOneRepMaxPreviewGoalField()->getLabel())],
        );

        if ($validator->fails()) {
            $this->setErrorBag($validator->errors()->merge(
                $this->getErrorBag()->except('oneRepMaxPreview.targetGoal')
            ));
            $this->addError('oneRepMaxPreview.targetGoal', $validator->errors()->first('targetGoal'));

            return;
        }

        $this->resetValidation('oneRepMaxPreview.targetGoal');

        $goal = (int) $validator->validated()['targetGoal'];

        $this->oneRepMaxPreview['targetGoal'] = $goal;
        $this->oneRepMaxPreview['appliedTargetGoal'] = $goal;

        unset($this->oneRepMaxPreviewGrid);
    }

    #[Computed]
    public function readinessSnapshot(): array
    {
        return $this->snapshotService()->currentSnapshot($this->record, MetricEnum::Readiness);
    }

    #[Computed]
    public function readinessViewData(): ?array
    {
        $snapshot = $this->readinessSnapshot;

        if (! ($snapshot['isAvailable'] ?? false)) {
            return null;
        }

        return ReadinessSurvey::buildViewData($snapshot['fieldValues'] ?? []);
    }

    #[Computed]
    public function heartRateSnapshot(): array
    {
        return $this->snapshotService()->currentSnapshot($this->record, MetricEnum::HeartRate);
    }

    #[Computed]
    public function heartRatePreviewSections(): array
    {
        $metric = $this->heartRateSnapshot['instance'] ?? null;

        if (! $metric instanceof HeartRateMetric) {
            return app(HeartRatePreviewBuilder::class)->buildSections(null, null);
        }

        return app(HeartRatePreviewBuilder::class)->buildSections(
            $metric->heartRate,
            $metric->anaerobicThreshold,
        );
    }

    #[Computed]
    public function oneRepMaxSnapshot(): array
    {
        return $this->snapshotService()->currentSnapshot($this->record, MetricEnum::OneRepMax);
    }

    #[Computed]
    public function oneRepMaxPreviewGoalField(): Number
    {
        return $this->resolveOneRepMaxPreviewGoalField();
    }

    #[Computed]
    public function oneRepMaxPreviewGrid(): mixed
    {
        $metric = $this->oneRepMaxSnapshot['instance'] ?? null;

        return app(OneRepMaxExamplePreviewBuilder::class)->build(
            $metric instanceof OneRepMaxMetric ? $metric : null,
            targetGoal: (int) ($this->oneRepMaxPreview['appliedTargetGoal'] ?? 10),
        );
    }

    public function metricTabRoute(MetricEnum $metric): string
    {
        return match ($metric) {
            MetricEnum::Readiness => 'readiness',
            MetricEnum::HeartRate => 'heart_rate',
            MetricEnum::OneRepMax => 'one_rep_max',
        };
    }

    protected function resolveAthlete(): User
    {
        return User::query()
            ->whereKey($this->record)
            ->where('type', UserTypeEnum::Athlete)
            ->firstOrFail();
    }

    protected function snapshotService(): AthleteMetricSnapshotService
    {
        return app(AthleteMetricSnapshotService::class);
    }

    protected function initializeOneRepMaxPreview(): void
    {
        $field = $this->resolveOneRepMaxPreviewGoalField();
        $defaultGoal = (int) ($field->default ?? 10);

        $this->oneRepMaxPreview = array_replace([
            'targetGoal' => $defaultGoal,
            'appliedTargetGoal' => $defaultGoal,
        ], $this->oneRepMaxPreview);
    }

    protected function resolveOneRepMaxPreviewGoalField(): Number
    {
        $field = collect(CategoryBlockType::fields(['categorySlug' => 'strength']))
            ->first(fn (mixed $field) => $field instanceof Number && $field->name === 'config.goal');

        if (! $field instanceof Number) {
            throw new \RuntimeException('Unable to resolve the strength goal field definition.');
        }

        $field = clone $field;
        $field->name = 'targetGoal';
        $field->label('Preview Goal');

        return $field;
    }

    /** @return array<int, string> */
    protected function oneRepMaxPreviewGoalRules(): array
    {
        $field = $this->resolveOneRepMaxPreviewGoalField();

        $rules = ['integer'];

        if ($field->required) {
            array_unshift($rules, 'required');
        }

        if ($field->min !== null) {
            $rules[] = 'min:'.(int) $field->min;
        }

        if ($field->max !== null) {
            $rules[] = 'max:'.(int) $field->max;
        }

        return $rules;
    }

    public function render(): View
    {
        return view('livewire.database.athlete-details-page');
    }
}
