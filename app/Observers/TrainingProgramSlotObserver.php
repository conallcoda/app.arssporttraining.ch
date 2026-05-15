<?php

namespace App\Observers;

use App\Models\Training\TrainingProgramSlot;
use App\Training\TrainingSessionMaterializer;
use App\Training\TrainingSessionRebuildService;

class TrainingProgramSlotObserver
{
    public function created(TrainingProgramSlot $slot): void
    {
        app(TrainingSessionMaterializer::class)->materialize($slot);
        $this->rebuildSiblingFutureSlots(
            trainingProgramId: (int) $slot->training_program_id,
            userId: (int) $slot->user_id,
        );
    }

    public function updated(TrainingProgramSlot $slot): void
    {
        if (! $slot->wasChanged(['training_program_id', 'user_id', 'datetime', 'cancelled_at'])) {
            return;
        }

        if ($slot->cancelled_at === null) {
            app(TrainingSessionMaterializer::class)->materialize($slot);
        }

        $originalTrainingProgramId = (int) ($slot->getOriginal('training_program_id') ?? 0);
        $originalUserId = (int) ($slot->getOriginal('user_id') ?? 0);
        $originalDateTime = $slot->getOriginal('datetime');

        if ($originalTrainingProgramId > 0 && $originalUserId > 0 && $originalDateTime !== null) {
            $this->rebuildSiblingFutureSlots(
                trainingProgramId: $originalTrainingProgramId,
                userId: $originalUserId,
            );
        }

        $this->rebuildSiblingFutureSlots(
            trainingProgramId: (int) $slot->training_program_id,
            userId: (int) $slot->user_id,
        );
    }

    public function deleted(TrainingProgramSlot $slot): void
    {
        $this->rebuildSiblingFutureSlots(
            trainingProgramId: (int) $slot->training_program_id,
            userId: (int) $slot->user_id,
        );
    }

    private function rebuildSiblingFutureSlots(int $trainingProgramId, int $userId): void
    {
        if ($trainingProgramId <= 0 || $userId <= 0) {
            return;
        }

        app(TrainingSessionRebuildService::class)->rebuildFutureSlotsForTrainingProgramAthlete(
            $trainingProgramId,
            $userId,
        );
    }
}
