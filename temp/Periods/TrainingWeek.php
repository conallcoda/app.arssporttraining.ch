<?php

namespace App\Models\Training\Periods;

use App\Models\Training\Periods\Data\TrainingPeriodIdentity;
use App\Models\Training\TrainingPeriod;
use App\Models\Training\TrainingPeriodData;
use Spatie\LaravelData\Attributes\DataCollectionOf;

class TrainingWeek extends TrainingPeriodData
{
    public function __construct(
        public TrainingPeriodIdentity $identity,
        public int $sequence = 0,
        #[DataCollectionOf(TrainingSession::class)]
        public array $children = [],
    ) {}

    public function name(): string
    {
        return "Week " . ($this->sequence + 1);
    }

    public static function fromModel(TrainingPeriod $model, array $extra = [])
    {
        static::guardAgainstInvalidType($model);
        $instance = new static(
            identity: static::createIdentity($model),
            sequence: $extra['sequence'],
        );
        return static::passParentAndSequence($instance, $model);
    }

    public static function fromConfig(array $data)
    {
        $instance = new static(
            sequence: $data['sequence'],
            identity: static::createIdentity(),
        );
        return static::passParentAndSequence($instance, $data);
    }

    public static function getModelType(): string
    {
        return 'week';
    }

    public function getModelData(): array
    {
        return [
            'sequence' => $this->sequence,
        ];
    }

    public static function getChildClass(): ?string
    {
        return TrainingSession::class;
    }
}
