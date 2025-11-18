<?php

namespace App\Models\Training;

use App\Data\AbstractData;
use App\Models\Training\Data\TrainingData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Computed;

class TrainingNode extends AbstractData
{
;

    public function __construct(
        public string $uuid,
        public ?int $id = null,
        public ?string $name,
        public int $sequence = 0,
        public string $type,
        TrainingData $data,
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
            type: $model->type,
            data: $model->toData(),
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

        $node = new static(
            uuid: TrainingPeriod::createUuid(),
            id: null,
            type: $data->getModelType(),
            data: $data,
            name: null,
            sequence: $sequence,
            children: $children,
        );

        $node->_additional['data'] = $data;

        return $node;
    }

    public function save(?int $parentId = null): TrainingPeriod
    {
        $data = $this->_additional['data'] ?? null;

        if (!$data instanceof TrainingData) {
            throw new \InvalidArgumentException("TrainingNode must have associated TrainingData to save");
        }

        $period = TrainingPeriod::create([
            'uuid' => $this->uuid,
            'name' => $this->name ?? $data->name($this),
            'type' => $data->getModelType(),
            'sequence' => $this->sequence,
            'parent_id' => $parentId,
            'extra' => $data->toArray(),
        ]);

        $this->id = $period->id;

        foreach ($this->children as $child) {
            $child->save($period->id);
        }

        return $period;
    }
}
