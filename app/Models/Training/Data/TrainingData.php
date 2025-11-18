<?php

namespace App\Models\Training\Data;

use App\Data\AbstractData;
use App\Models\Training\TrainingNode;
use App\Models\Training\TrainingPeriod;

abstract class TrainingData extends AbstractData
{
    abstract public function name(TrainingNode $node): string;

    abstract static public function getModelType(): string;

    public static function guardAgainstInvalidType(TrainingPeriod $model)
    {
        if ($model->type !== static::getModelType()) {
            throw new \InvalidArgumentException("Invalid season type: {$model->type}");
        }
    }

    public function withChildren(array $children)
    {
        return $this->additional(['children' => $children]);
    }
}
