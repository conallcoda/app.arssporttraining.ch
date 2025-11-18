<?php

namespace App\Models\Training\Data;

use App\Models\Training\TrainingNode;
use App\Models\Training\TrainingPeriod;

class WeekData extends TrainingData
{
    public function name(TrainingNode $node): string
    {
        return "Week " . ($node->sequence + 1);
    }
    static public function getModelType(): string
    {
        return 'week';
    }

    public static function fromModel(TrainingPeriod $model)
    {
        static::guardAgainstInvalidType($model);
        $instance = new static();
        return $instance;
    }
}
