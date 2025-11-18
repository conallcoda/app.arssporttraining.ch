<?php

namespace App\Models\Training\Periods;

use App\Models\Training\TrainingPeriod;
use App\Models\Training\TrainingPeriodData;
use App\Data\Model\ModelIdentity;

class TrainingSeason extends TrainingPeriodData
{
    public function __construct(
        public ?ModelIdentity $identity = null,
        public string $name = '',
        public array $children = []
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public static function fromModel(TrainingPeriod $model)
    {
        static::guardAgainstInvalidType($model);
        $instance = new static(
            name: $model->name,
            identity: ModelIdentity::fromModel($model),
        );
        return static::passParentAndSequence($instance, $model);
    }

    public static function fromConfig(array $data)
    {
        $instance = new static(
            name: $data['name'] ?? '',
            identity: $data['identity'] ?? null,
        );

        return static::passParentAndSequence($instance, $data);
    }

    public static function getChildClass(): ?string
    {
        return TrainingBlock::class;
    }

    public static function getModelType(): string
    {
        return 'season';
    }

    public function getModelData(): array
    {
        return [
            'name' => $this->name,
        ];
    }
}
