<?php

namespace App\Livewire\Database;

use App\Data\Athlete\Metric\MetricEnum;
use App\Data\Athlete\Metric\Metrics\HeartRateMetric;
use App\Data\Athlete\Metric\Metrics\OneRepMaxMetric;
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
use Flux\Flux;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Livewire\Attributes\Computed;

class AthleteDetailsPage extends AbstractModelDetailsPage
{
    public function mount(int $record): void
    {
        parent::mount($record);
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
    public function oneRepMaxPreviewGrid(): mixed
    {
        $metric = $this->oneRepMaxSnapshot['instance'] ?? null;

        return app(OneRepMaxExamplePreviewBuilder::class)->build(
            $metric instanceof OneRepMaxMetric ? $metric : null,
            targetGoal: 10,
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

    public function render(): View
    {
        return view('livewire.database.athlete-details-page');
    }
}
