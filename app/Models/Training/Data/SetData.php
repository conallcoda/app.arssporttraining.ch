<?php

namespace App\Models\Training\Data;

use App\Models\Training\TrainingNode;
use App\Models\Training\TrainingPeriod;

class SetData extends TrainingData
{
    public function __construct(
        public int $reps,
        public float $weight,
    ) {}

    public function name(TrainingNode $node): string
    {
        return "" . $this->reps . " reps x " . $this->weight . " kg";
    }
    static public function getModelType(): string
    {
        return 'set';
    }

    public function toArray(): array
    {
        return ['reps' => $this->reps, 'weight' => $this->weight];
    }

    public static function fromModel(TrainingPeriod $model)
    {
        static::guardAgainstInvalidType($model);
        $instance = new static(
            reps: $model->extra->reps,
            weight: $model->extra->weight
        );
        return $instance;
    }
}
