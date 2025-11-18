<?php

namespace App\Models\Training\Data;

use App\Models\Training\TrainingNode;
use App\Models\Training\TrainingPeriod;

class SeasonData extends TrainingData
{
    public function name(TrainingNode $node): string
    {
        return $node->name;
    }
    static public function getModelType(): string
    {
        return 'season';
    }

    public static function fromModel(TrainingPeriod $model)
    {
        static::guardAgainstInvalidType($model);
        $instance = new static();
        return $instance;
    }
}
