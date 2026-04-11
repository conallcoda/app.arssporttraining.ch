<?php

namespace App\Models\Training;

enum TrainingProgramSlotSetStatusEnum: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case CompletedWithModification = 'completed_with_modification';
    case Skipped = 'skipped';
}
