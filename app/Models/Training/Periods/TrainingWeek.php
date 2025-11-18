<?php

namespace App\Models\Training\Periods;

use App\Data\Model\ModelIdentity;
use App\Models\Training\TrainingPeriod;
use App\Models\Training\TrainingPeriodData;
use Spatie\LaravelData\Attributes\DataCollectionOf;

class TrainingWeek extends TrainingPeriodData
{
    public function __construct(
        public ?ModelIdentity $identity = null,
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
            identity: ModelIdentity::fromModel($model),
            sequence: $extra['sequence'],
        );
        return static::passParentAndSequence($instance, $model);
    }

    public static function fromConfig(array $data)
    {
        $instance = new static(
            sequence: $data['sequence'],
            identity: $data['identity'] ?? null,
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
