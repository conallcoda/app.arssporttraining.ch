<?php

namespace App\Models\Training\Periods;

use App\Models\Training\TrainingPeriod;
use App\Models\Training\TrainingPeriodData;
use App\Models\Training\Periods\Data\TrainingPeriodIdentity;

class TrainingSeason extends TrainingPeriodData
{
    public function __construct(
        public TrainingPeriodIdentity $identity,
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
            identity: static::createIdentity($model)
        );
        return static::passParentAndSequence($instance, $model);
    }


    public static function fromConfig(array $data)
    {
        $instance = new static(
            name: $data['name'] ?? '',
            identity: static::createIdentity()
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
