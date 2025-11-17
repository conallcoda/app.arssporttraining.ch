<?php

namespace App\Models\Training\Periods;

use App\Models\Training\TrainingPeriod;
use App\Models\Training\TrainingPeriodData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use App\Data\Model\ModelIdentity;

class TrainingSeason extends TrainingPeriodData
{
    public function __construct(
        public ?ModelIdentity $identity,
        public string $name,
        #[DataCollectionOf(TrainingBlock::class)]
        public array $children = [],
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public static function fromModel(TrainingPeriod $model)
    {
        static::guardAgainstInvalidType($model, 'season');
        return new static(
            identity: static::identityFromModel($model),
            name: $model->name,
        );
    }

    public function persist()
    {
        $data = [
            'name' => $this->name,
            'type' => 'season',
        ];
        if ($this->identity) {
            $model = TrainingPeriod::findOrFail($this->identity->id);
            $model->fill($data);
        } else {
            $model = TrainingPeriod::make($data);
        }

        $model->save();
        $this->identity = static::identityFromModel($model);
    }
}
