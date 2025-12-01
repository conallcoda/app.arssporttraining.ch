<?php

namespace App\Models\Training\Actions\Exercise;

use App\Models\Training\Actions\Action;
use App\Models\Training\Actions\Concerns\HasAddEventBehavior;
use App\Models\Training\TrainingNode;

class ExerciseAddedEvent extends Action
{
    use HasAddEventBehavior;

    public function __construct(
        public TrainingNode $parent,
        public TrainingNode $child,
    ) {}
}
