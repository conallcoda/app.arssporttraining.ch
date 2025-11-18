<?php

namespace App\Models\Training\Periods\Data;

use App\Data\AbstractData;
use App\Data\Model\ModelIdentity;
use App\Models\Training\TrainingSessionCategory;

class TrainingSessionCategoryData extends AbstractData
{
    public function __construct(
        public ?ModelIdentity $identity,
        public string $name,
        public ?string $background_color = '#000000',
        public ?string $text_color = '#FFFFFF'
    ) {}

    public static function fromModel(TrainingSessionCategory $model): static
    {
        return new self(
            identity: ModelIdentity::fromModel($model),
            name: $model->name,
            background_color: $model->background_color,
            text_color: $model->text_color,
        );
    }

    public static function fromId(int $id)
    {
        return static::fromModel(TrainingSessionCategory::findOrFail($id));
    }
}
