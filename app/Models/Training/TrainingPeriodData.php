<?php

namespace App\Models\Training;

use App\Data\AbstractData;
use App\Data\Model\ModelIdentity;
use App\Models\Training\Periods\TrainingExercise;

abstract class TrainingPeriodData extends AbstractData
{

    abstract public function name(): string;



    public static function passParent($parent, array $data): static
    {
        if (empty($data['children']) || is_null(static::getChildClass())) {
            return $parent;
        }

        $children = collect($data['children'] ?? [])
            ->map(function ($childData) use ($parent) {
                return static::getChildClass()::from(array_merge($childData, ['parent' => $parent]));
            })
            ->all();

        $parent->children = $children;
        return $parent;
    }

    public static function passParentAndSquence($parent, array $data): static
    {
        if (empty($data['children']) || is_null(static::getChildClass())) {
            return $parent;
        }


        $children = collect($data['children'] ?? [])
            ->map(function ($childData, $index) use ($parent) {
                return static::getChildClass()::from(array_merge($childData, ['sequence' => $index, 'parent' => $parent]));
            })
            ->all();

        $parent->children = $children;
        return $parent;
    }

    public static function getChildClass(): ?string
    {
        return null;
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

    public function persist()
    {
        $modelClass = static::getModelClass();
        $data = $this->getModelData();
        $data = array_merge($data, [
            'type' => static::getModelType(),
        ]);

        if ($identity = $this->getIdentity()) {
            $model = $modelClass::findOrFail($identity->id);
            $model->fill($data);
        } else {
            $model = $modelClass::make($data);
        }

        $model->save();
        $this->identity = ModelIdentity::from($model);
    }
}
