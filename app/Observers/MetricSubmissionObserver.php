<?php

namespace App\Observers;

use App\Data\Athlete\Metric\MetricEnum;
use App\Models\Athlete\MetricSubmission;
use App\Training\TrainingSessionRebuildDispatcher;

class MetricSubmissionObserver
{
    public function saved(MetricSubmission $submission): void
    {
        $this->rebuildFutureSlots($submission);
    }

    public function deleted(MetricSubmission $submission): void
    {
        $this->rebuildFutureSlots($submission);
    }

    private function rebuildFutureSlots(MetricSubmission $submission): void
    {
        if (! in_array($submission->metric, [MetricEnum::OneRepMax, MetricEnum::HeartRate], true)) {
            return;
        }

        if ($submission->owner_type !== null && $submission->owner_type !== \App\Models\Users\User::class) {
            return;
        }

        app(TrainingSessionRebuildDispatcher::class)
            ->dispatchFutureSlotsForAthlete(
                $submission->user_id,
                $submission->recorded_at?->format('Y-m-d')
            );
    }
}
