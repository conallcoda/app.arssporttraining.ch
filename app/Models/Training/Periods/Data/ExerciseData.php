<?php

namespace App\Models\Training\Periods\Data;

use App\Data\AbstractData;
use App\Data\Model\ModelIdentity;
use App\Models\Exercise\Exercise;

class ExerciseData extends AbstractData
{
    public function __construct(
        public ?ModelIdentity $identity,
        public string $name,
    ) {}

    public static function fromModel(Exercise $model)
    {
        return new static(
            identity: ModelIdentity::fromModel($model),
            name: $model->name,
        );
    }
}
