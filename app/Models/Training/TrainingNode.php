<?php

namespace App\Models\Training;

use App\Data\AbstractData;
use App\Models\Training\Data\TrainingData;
use Spatie\LaravelData\Attributes\DataCollectionOf;

class TrainingNode extends AbstractData
{
    public function __construct(
        public string $uuid,
        public ?int $id = null,
        public ?string $name,
        public int $sequence = 0,
        #[DataCollectionOf(TrainingNode::class)]
        public array $children = [],
    ) {}

    public static function fromModel(TrainingPeriod $model): self
    {
        $children = [];
        foreach ($model->children as $child) {
            $children[] = static::fromModel($child);
        }

        return new static(
            uuid: $model->uuid,
            id: $model->id,
            name: $model->name,
            sequence: $model->sequence,
            children: $children,
        );
    }

    public static function fromData(TrainingData $data, int $sequence = 0): self
    {
        $children = [];

        if (isset($data->_additional['children']) && is_array($data->_additional['children'])) {
            foreach ($data->_additional['children'] as $i => $childData) {
                if ($childData instanceof TrainingData) {
                    $children[] = static::fromData($childData, $i);
                }
            }
        }

        return new static(
            uuid: TrainingPeriod::createUuid(),
            id: null,
            name: null,
            sequence: $sequence,
            children: $children,
        );
    }

    public function save() {}
}
