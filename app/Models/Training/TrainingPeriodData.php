<?php

namespace App\Models\Training;

use App\Data\AbstractData;
use App\Data\Model\ModelIdentity;
use App\Models\Training\Periods\Data\TrainingPeriodIdentity;
use App\Models\Training\Periods\TrainingExercise;

abstract class TrainingPeriodData extends AbstractData
{

    abstract public function name(): string;

    public static function passParentAndSequence($instance, array|TrainingPeriod $data): static
    {
        if (is_null(static::getChildClass())) {
            return $instance;
        }

        if ($data instanceof TrainingPeriod) {
            $children = $data->children()->get()->map(function ($model, $index) {
                return static::getChildClass()::fromModel($model, ['sequence' => $index]);
            })->all();
        } else {
            $children = collect($data['children'] ?? [])->map(function ($childData, $index) {
                return static::getChildClass()::fromConfig(array_merge($childData, ['sequence' => $index]));
            })->all();
        }

        $instance->children = $children;
        return $instance;
    }

    public static function getChildClass(): ?string
    {
        return null;
    }

    public static function createIdentity(?TrainingPeriod $model = null)
    {
        return isset($model) ?
            TrainingPeriodIdentity::fromModel($model) :
            TrainingPeriodIdentity::fromType(static::getModelType());
    }


    public static function guardAgainstInvalidType(TrainingPeriod $model)
    {
        if ($model->type !== static::getModelType()) {
            throw new \InvalidArgumentException("Invalid season type: {$model->type}");
        }
    }

    public static function getModelClass(): string
    {
        return TrainingPeriod::class;
    }

    public function getModelData(): array
    {
        return [];
    }

    public function getIdentity(): ?ModelIdentity
    {
        return $this->identity ?? null;
    }

    abstract public static function getModelType(): string;

    public function getChildren(): array
    {
        return property_exists($this, 'children') ? $this->{'children'} : [];
    }

    public function persist(?int $parentId = null)
    {
        $modelClass = static::getModelClass();
        $data = $this->getModelData();
        $data = array_merge($data, [
            'type' => static::getModelType(),
            'parent_id' => $parentId,
        ]);

        if ($identity = $this->getIdentity()) {
            $model = $modelClass::findOrFail($identity->id);
            $model->fill($data);
        } else {
            $model = $modelClass::make($data);
        }

        $model->save();

        if (property_exists($this, 'identity')) {
            $this->{'identity'} = ModelIdentity::from($model);
        }

        foreach ($this->getChildren() as $child) {
            if ($child instanceof TrainingPeriodData) {
                $child->persist($model->id);
            }
        }
    }
}
