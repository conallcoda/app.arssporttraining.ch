<?php

namespace App\Models\Training;

use App\Data\AbstractData;
use App\Models\Training\Data\BlockData;
use App\Models\Training\Data\ExerciseData;
use App\Models\Training\Data\SeasonData;
use App\Models\Training\Data\SessionData;
use App\Models\Training\Data\SetData;
use App\Models\Training\Data\TrainingData;
use App\Models\Training\Data\WeekData;
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
        public ?array $path = null,
        public ?string $linked_to = null,
    ) {
        self::$registry[$this->uuid] = $this;
    }

    public static function prepareForPipeline(array $properties): array
    {
        if (isset($properties['data']) && is_array($properties['data']) && isset($properties['type'])) {
            $typeMap = [
                'season' => SeasonData::class,
                'block' => BlockData::class,
                'week' => WeekData::class,
                'session' => SessionData::class,
                'exercise' => ExerciseData::class,
                'set' => SetData::class,
            ];

            if (isset($typeMap[$properties['type']])) {
                $dataClass = $typeMap[$properties['type']];
                $properties['data'] = $dataClass::from($properties['data']);
            }
        }

        return $properties;
    }

    public static function fromModel(TrainingPeriod $model, ?string $parentUuid = null, array $path = []): self
    {
        if (isset(self::$registry[$model->uuid])) {
            return self::$registry[$model->uuid];
        }

        $children = [];
        $childParentUuid = in_array($model->type, ['season']) ? null : $model->uuid;
        $childPath = in_array($model->type, ['season']) ? [$model->uuid] : array_merge($path, [$model->uuid]);
        foreach ($model->children as $child) {
            $children[] = static::fromModel($child, $childParentUuid, $childPath);
        }

        $instance = new static(
            uuid: $model->uuid,
            id: $model->id,
            parent: $parentUuid,
            type: $model->type,
            data: $model->toData(),
            name: $model->name,
            sequence: $model->sequence,
            children: $children,
            path: $model->type === 'season' ? null : $path,
            linked_to: $model->linked_to,
        );

        self::$registry[$model->uuid] = $instance;

        return $instance;
    }


    public static function fromData(TrainingData $data, int $sequence = 0, ?string $parentUuid = null, array $path = []): self
    {
        $uuid = TrainingPeriod::createUuid();
        $type = $data->getModelType();
        $children = [];

        if (isset($data->_additional['children']) && is_array($data->_additional['children'])) {
            $childParentUuid = in_array($type, ['season']) ? null : $uuid;
            $childPath = in_array($type, ['season']) ? [$uuid] : array_merge($path, [$uuid]);
            foreach ($data->_additional['children'] as $i => $childData) {
                if ($childData instanceof TrainingData) {
                    $children[] = static::fromData($childData, $i, $childParentUuid, $childPath);
                }
            }
        }

        $instance = new static(
            uuid: $uuid,
            id: null,
            parent: $parentUuid,
            type: $type,
            data: $data,
            name: null,
            sequence: $sequence,
            children: $children,
            path: $type === 'season' ? null : $path,
        );

        self::$registry[$uuid] = $instance;

        return $instance;
    }

    public function save(?int $parentId = null): void
    {
        $extraData = $this->isLinked() ? null : $this->data->toArray();
        $extraData = empty($extraData) ? null : $extraData;

        if ($this->id !== null) {
            $period = TrainingPeriod::findOrFail($this->id);
            $period->update([
                'name' => $this->name ?? null,
                'type' => $this->type,
                'sequence' => $this->sequence,
                'parent_id' => $parentId,
                'extra' => $extraData,
                'linked_to' => $this->linked_to,
            ]);
        } else {
            $period = TrainingPeriod::create([
                'uuid' => $this->uuid,
                'name' => $this->name ?? null,
                'type' => $this->type,
                'sequence' => $this->sequence,
                'parent_id' => $parentId,
                'extra' => $extraData,
                'linked_to' => $this->linked_to,
            ]);

            $this->id = $period->id;
        }

        if (!$this->isLinked()) {
            foreach ($this->children as $child) {
                $child->save($period->id);
            }
        }
    }

    public function getIdentity(): ?object
    {
        return $this->id ? (object)['id' => $this->id] : null;
    }

    public function getChildren(): array
    {
        if ($this->linked_to !== null) {
            $source = $this->getLinkedSource();
            if ($source !== null) {
                return $source->children;
            }
        }
        return $this->children;
    }

    public function getData(): TrainingData
    {
        if ($this->linked_to !== null) {
            $source = $this->getLinkedSource();
            if ($source !== null) {
                if ($this->type === 'session') {
                    $sourceData = $source->data->toArray();
                    $sourceData['day'] = $this->data->day;
                    $sourceData['slot'] = $this->data->slot;
                    return SessionData::from($sourceData);
                }
                if ($this->type === 'week') {
                    return $this->data;
                }
                return $source->data;
            }
        }
        return $this->data;
    }

    public function getLinkedSource(): ?TrainingNode
    {
        if ($this->linked_to === null) {
            return null;
        }
        return self::$registry[$this->linked_to] ?? null;
    }

    public function isLinked(): bool
    {
        return $this->linked_to !== null;
    }

    public function findChild($uuid): ?TrainingNode
    {
        foreach ($this->children as $child) {
            if ($child->uuid === $uuid) {
                return $child;
            }
        }
        return null;
    }

    public function createLinkedClone(?string $parentUuid = null, int $sequence = 0): TrainingNode
    {
        $clone = new static(
            uuid: TrainingPeriod::createUuid(),
            id: null,
            parent: $parentUuid,
            type: $this->type,
            data: $this->data::from($this->data->toArray()),
            name: $this->name,
            sequence: $sequence,
            children: [],
            path: $this->path,
            linked_to: $this->uuid,
        );

        self::$registry[$clone->uuid] = $clone;

        return $clone;
    }

    public static function clearRegistry(): void
    {
        self::$registry = [];
    }

    public static function getFromRegistry(string $uuid): ?TrainingNode
    {
        return self::$registry[$uuid] ?? null;
    }

    public function name(): string
    {
        return $this->data->name($this);
    }
}
