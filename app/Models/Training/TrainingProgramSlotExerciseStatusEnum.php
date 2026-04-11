<?php

namespace App\Models\Training;

enum TrainingProgramSlotExerciseStatusEnum: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case PartiallyCompleted = 'partially_completed';
    case Skipped = 'skipped';
}
