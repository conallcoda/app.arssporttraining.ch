<?php

namespace App\Models\Training;

enum TrainingProgramSlotStatusEnum: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case PartiallyCompleted = 'partially_completed';
    case Skipped = 'skipped';
    case Cancelled = 'cancelled';
}
