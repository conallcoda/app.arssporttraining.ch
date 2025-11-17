<?php

namespace App\Models\Training\Periods;

use App\Models\Training\TrainingPeriodData;
use App\Data\Model\ModelIdentity;

class TrainingExercise extends TrainingPeriodData
{
    public static string $type = 'exercise';

    public function __construct(
        public ?ModelIdentity $identity,
        public ?ModelIdentity $exercise,
        public int $sequence,
    ) {}

    public function name(): string
    {
        return "Exercise {$this->sequence}";
    }
}
