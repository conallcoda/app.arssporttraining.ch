<?php

namespace App\Models\Training\Data;

use App\Models\Training\TrainingNode;
use App\Models\Training\TrainingPeriod;

class BlockData extends TrainingData
{
    public function name(TrainingNode $node): string
    {
        return "Block " . ($node->sequence + 1);
    }
    static public function getModelType(): string
    {
        return 'block';
    }

    public static function fromModel(TrainingPeriod $model)
    {
        static::guardAgainstInvalidType($model);
        $instance = new static();
        return $instance;
    }
}
