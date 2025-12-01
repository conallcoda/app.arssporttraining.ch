<?php

namespace App\Models\Training\Data;

use App\Models\Training\TrainingNode;
use App\Models\Training\TrainingPeriod;

class WeekData extends TrainingData
{
    public function __construct() {}

    public function name(TrainingNode $node): string
    {
        return 'Week '.($node->sequence + 1);
    }

    public static function getModelType(): string
    {
        return 'week';
    }

    public function toArray(): array
    {
        return [];
    }

    public static function fromModel(TrainingPeriod $model)
    {
        static::guardAgainstInvalidType($model);

        return new static;
    }
}
