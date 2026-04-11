<?php

namespace App\Observers;

use App\Models\Training\TrainingProgramSlot;
use App\Training\TrainingSessionMaterializer;

class TrainingProgramSlotObserver
{
    public function created(TrainingProgramSlot $slot): void
    {
        app(TrainingSessionMaterializer::class)->materialize($slot);
    }

    public function updated(TrainingProgramSlot $slot): void
    {
        if (! $slot->wasChanged(['training_program_id', 'user_id', 'datetime'])) {
            return;
        }

        app(TrainingSessionMaterializer::class)->materialize($slot);
    }
}
