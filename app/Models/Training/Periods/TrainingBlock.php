<?php

namespace App\Models\Training\Periods;

use App\Models\Training\TrainingPeriodData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use App\Data\Model\ModelIdentity;
use App\Models\Training\TrainingPeriod;

class TrainingBlock extends TrainingPeriodData
{
    public static string $type = 'block';

    public function __construct(
        public TrainingSeason $parent,
        public ?ModelIdentity $identity = null,
        public int $sequence = 0,
        #[DataCollectionOf(TrainingWeek::class)]
        public array $children = [],
    ) {}

    public function name(): string
    {
        return "Block " . ($this->sequence + 1);
    }

    public static function fromModel(TrainingPeriod $model, array $extra = [])
    {
        static::guardAgainstInvalidType($model);
        $instance = new static(
            parent: $extra['parent'],
            identity: ModelIdentity::fromModel($model),
            sequence: $extra['sequence'],
        );
        return static::passParentAndSequence($instance, $model);
    }

    public static function fromConfig(array $data)
    {
        $instance = new static(
            parent: $data['parent'],
            sequence: $data['sequence'],
            identity: $data['identity'] ?? null,
        );
        return static::passParentAndSequence($instance, $data);
    }

    public function getModelData(): array
    {
        return [
            'sequence' => $this->sequence,
        ];
    }

    public static function getModelType(): string
    {
        return 'block';
    }

    public static function getChildClass(): ?string
    {
        return TrainingWeek::class;
    }
}
