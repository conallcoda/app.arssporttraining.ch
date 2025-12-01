<?php

namespace App\Models\Training\Actions\Session;

use App\Models\Training\Actions\Action;
use App\Models\Training\Actions\Concerns\HasAddEventBehavior;
use App\Models\Training\TrainingNode;

class SessionAddedEvent extends Action
{
    use HasAddEventBehavior;

    public function __construct(
        public TrainingNode $parent,
        public TrainingNode $child,
    ) {}
}
