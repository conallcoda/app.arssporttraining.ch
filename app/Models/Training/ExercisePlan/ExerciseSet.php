<?php

namespace App\Models\Training\ExercisePlan;

use App\Data\AbstractData;

class ExerciseSet extends AbstractData
{
    public function __construct(
        public ?int $reps = null,
        public ?float $weight = null,
        public ?float $oneRepMax = null,
    ) {}
}
