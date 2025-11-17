<?php

namespace App\Models\Training;

use App\Data\AbstractData;
use App\Data\Model\ModelIdentity;

abstract class TrainingPeriodData extends AbstractData
{

    abstract public function name(): string;

    public static function identityFromModel(TrainingPeriod $model): ModelIdentity
    {
        return new ModelIdentity(
            id: $model->id,
            model: get_class($model),
        );
    }

    public static function guardAgainstInvalidType(TrainingPeriod $model, $type)
    {
        if ($model->type !== $type) {
            throw new \InvalidArgumentException("Invalid season type: {$model->type}");
        }
    }
}
