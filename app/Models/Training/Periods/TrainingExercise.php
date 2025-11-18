<?php

namespace App\Models\Training\Periods;

use App\Models\Training\TrainingPeriodData;
use App\Data\Model\ModelIdentity;
use App\Models\Training\Periods\Data\ExerciseData;
use App\Models\Training\TrainingPeriod;

class TrainingExercise extends TrainingPeriodData
{
    public function __construct(
        public TrainingSession $parent,
        public ?ModelIdentity $identity = null,
        public int $sequence = 0,
        public ExerciseData $exercise,

    ) {}

    public static function fromModel(TrainingPeriod $model, array $extra = [])
    {
        static::guardAgainstInvalidType($model);
        $instance = new static(
            parent: $extra['parent'],
            identity: ModelIdentity::fromModel($model),
            sequence: $extra['sequence'],
            exercise: ExerciseData::from($model->extra['exercise']),
        );
        return static::passParentAndSequence($instance, $model);
    }

    public static function fromConfig(array $data)
    {
        return new static(
            parent: $data['parent'],
            identity: $data['identity'] ?? null,
            exercise: ExerciseData::from($data['exercise']),
            sequence: $data['sequence'],
        );
    }

    public static function getModelType(): string
    {
        return 'exercise';
    }

    public function getModelData(): array
    {
        return [
            'extra' => ['exercise' => $this->exercise->identity->id],
            'sequence' => $this->sequence,
        ];
    }

    public function name(): string
    {
        return $this->exercise->name;
    }
}
