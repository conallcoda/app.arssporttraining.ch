<?php

namespace App\Models\Training\Periods;

use App\Models\Training\Periods\Data\TrainingPeriodIdentity;
use App\Models\Training\Periods\Data\TrainingSessionPeriod;
use App\Models\Training\TrainingPeriod;
use App\Models\Training\TrainingPeriodData;
use Spatie\LaravelData\Attributes\DataCollectionOf;

class TrainingSession extends TrainingPeriodData
{
    public static string $type = 'session';

    public function __construct(
        public TrainingPeriodIdentity $identity,
        public ?TrainingSessionPeriod $period = null,
        #[DataCollectionOf(TrainingExercise::class)]
        public array $children = [],
    ) {}

    public function name(): string
    {
        return $this->period?->label() ?? 'Session';
    }

    public static function fromModel(TrainingPeriod $model, array $extra = [])
    {
        static::guardAgainstInvalidType($model);
        $instance = new static(
            identity: static::createIdentity($model),
            period: TrainingSessionPeriod::from($model->extra['period']),
        );

        return static::passParentAndSequence($instance, $model);
    }

    public static function fromConfig(array $data)
    {
        $model = new static(
            identity: static::createIdentity(),
            period: TrainingSessionPeriod::from($data['period']),
        );

        return static::passParentAndSequence($model, $data);
    }

    public static function getModelType(): string
    {
        return 'session';
    }

    public function getModelData(): array
    {
        return [
            'extra' => [
                'period' => $this->period->toArray(),
            ],
        ];
    }

    public static function getChildClass(): ?string
    {
        return TrainingExercise::class;
    }
}
