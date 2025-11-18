<?php

namespace App\Models\Training\Periods;

use App\Models\Training\TrainingPeriod;
use App\Models\Training\TrainingPeriodData;
use App\Data\Model\ModelIdentity;
use PhpParser\Node\Expr\AssignOp\Mod;

class TrainingSeason extends TrainingPeriodData
{
    public function __construct(
        public ?ModelIdentity $identity = null,
        public string $name = '',
        public array $children = [],
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public static function fromModel(TrainingPeriod $model)
    {
        static::guardAgainstInvalidType($model);
        return new static(
            identity: ModelIdentity::fromModel($model),
            name: $model->name,
        );
    }

    public static function fromConfig(array $data)
    {
        $model = new static(
            name: $data['name'] ?? '',
            identity: $data['identity'] ?? null,
        );

        return static::passParentAndSquence($model, $data);
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
