<?php

namespace App\Data\Model;

use App\Data\AbstractData;

class ModelIdentity extends AbstractData
{
    public function __construct(public int $id, public string $model) {}

    public static function fromModel(\Illuminate\Database\Eloquent\Model $model): static
    {
        return new static(
            id: $model->id,
            model: get_class($model),
        );
    }
}
