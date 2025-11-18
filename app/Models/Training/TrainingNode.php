<?php

namespace App\Models\Training;

use App\Data\AbstractData;
use App\Models\Training\Data\TrainingData;
use Spatie\LaravelData\Attributes\DataCollectionOf;

class TrainingNode extends AbstractData
{
    private static array $registry = [];

    public function __construct(
        public string $uuid,
        public ?int $id = null,
        public ?string $parent = null,
        public ?string $name,
        public int $sequence = 0,
        public string $type,
        public TrainingData $data,
        #[DataCollectionOf(TrainingNode::class)]
        public array $children = [],
    ) {}

    public static function fromModel(TrainingPeriod $model): self
    {
        if (isset(self::$registry[$model->uuid])) {
            return self::$registry[$model->uuid];
        }

        $children = [];
        foreach ($model->children as $child) {
            $children[] = static::fromModel($child);
        }

        $parent = null;
        if ($model->parent && !in_array($model->type, ['season'])) {
            $parent = $model->parent->uuid;
        }

        $instance = new static(
            uuid: $model->uuid,
            id: $model->id,
            parent: $parent,
            type: $model->type,
            data: $model->toData(),
            name: $model->name,
            sequence: $model->sequence,
            children: $children,
        );

        self::$registry[$model->uuid] = $instance;

        return $instance;
    }


    public static function fromData(TrainingData $data, int $sequence = 0, ?string $parentUuid = null): self
    {
        $uuid = TrainingPeriod::createUuid();
        $type = $data->getModelType();
        $children = [];

        if (isset($data->_additional['children']) && is_array($data->_additional['children'])) {
            $childParentUuid = in_array($type, ['season']) ? null : $uuid;
            foreach ($data->_additional['children'] as $i => $childData) {
                if ($childData instanceof TrainingData) {
                    $children[] = static::fromData($childData, $i, $childParentUuid);
                }
            }
        }

        return new static(
            uuid: $uuid,
            id: null,
            parent: $parentUuid,
            type: $type,
            data: $data,
            name: null,
            sequence: $sequence,
            children: $children,
        );
    }

    public function save(?int $parentId = null): void
    {
        if ($this->id !== null) {
            $period = TrainingPeriod::findOrFail($this->id);
            $period->update([
                'name' => $this->name ?? null,
                'type' => $this->type,
                'sequence' => $this->sequence,
                'parent_id' => $parentId,
                'extra' => $this->data->toArray(),
            ]);
        } else {
            $period = TrainingPeriod::create([
                'uuid' => $this->uuid,
                'name' => $this->name ?? null,
                'type' => $this->type,
                'sequence' => $this->sequence,
                'parent_id' => $parentId,
                'extra' => $this->data->toArray(),
            ]);

            $this->id = $period->id;
        }

        foreach ($this->children as $child) {
            $child->save($period->id);
        }
    }
}
