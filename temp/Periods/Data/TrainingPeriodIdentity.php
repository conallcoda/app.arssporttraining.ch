<?php

namespace App\Models\Training\Periods\Data;

use App\Data\AbstractData;
use App\Models\Training\TrainingPeriod;

class TrainingPeriodIdentity extends AbstractData
{
    public function __construct(
        public ?int $id,
        public string $type,
        public string $uuid,
    ) {}

    public static function fromModel(TrainingPeriod $model): static
    {
        return new self(
            id: $model->id,
            type: $model->type,
            uuid: $model->uuid,
        );
    }

    public static function fromType(string $type)
    {
        return new self(
            id: null,
            type: $type,
            uuid: TrainingPeriod::createUuid(),
        );
    }
}
