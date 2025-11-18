<?php

namespace App\Models\Training\Periods;

use App\Models\Training\TrainingPeriodData;
use App\Data\Model\ModelIdentity;
use App\Models\Training\Periods\Data\ExerciseData;

class TrainingExercise extends TrainingPeriodData
{
    public function __construct(
        public TrainingSession $parent,
        public ?ModelIdentity $identity = null,
        public int $sequence = 0,
        public ExerciseData $exercise,

    ) {}

    public static function fromConfig(array $data)
    {
        return new static(
            parent: $data['parent'],
            identity: $data['identity'] ?? null,
            exercise: ExerciseData::from($data['exercise']),
            sequence: $data['sequence'],
        );
    }

    public static function getModelType(): string
    {
        return 'exercise';
    }

    public function name(): string
    {
        return $this->exercise->name;
    }
}
