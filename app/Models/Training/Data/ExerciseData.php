<?php

namespace App\Models\Training\Data;

use App\Models\Training\TrainingNode;
use App\Models\Training\TrainingPeriod;

class ExerciseData extends TrainingData
{
    public function __construct(
        public int $exercise,
    ) {}

    public function name(TrainingNode $node): string
    {
        return "Exercise " . ($node->sequence + 1);
    }
    static public function getModelType(): string
    {
        return 'exercise';
    }

    public function toArray(): array
    {
        return ['exercise' => $this->exercise];
    }

    public static function fromModel(TrainingPeriod $model)
    {
        static::guardAgainstInvalidType($model);
        $instance = new static(
            exercise: $model->extra->exercise
        );
        return $instance;
    }
}
