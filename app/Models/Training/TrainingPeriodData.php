<?php

namespace App\Models\Training;

use App\Data\AbstractData;
use App\Data\Model\ModelIdentity;
use App\Models\Training\Periods\TrainingExercise;

abstract class TrainingPeriodData extends AbstractData
{

    abstract public function name(): string;

    public static function passParentAndSequence($parent, array|TrainingPeriod $data): static
    {
        if (is_null(static::getChildClass())) {
            return $parent;
        }
        if ($data instanceof TrainingPeriod) {
            $mapper = function ($model, $index = 0) use ($parent) {
                $extra = ['sequence' => $index, 'parent' => $parent];
                return static::getChildClass()::fromModel($model, $extra);
            };
            $children =  $data->children()->get();
        } else {
            $mapper =  function ($childData, $index  = 0) use ($parent) {
                $extra = ['sequence' => $index, 'parent' => $parent];
                return static::getChildClass()::fromConfig(array_merge($childData, $extra));
            };
            $children = $data['children'] ?? [];
        }
        $children = collect($children)
            ->map($mapper)
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

    public function getChildren(): array
    {
        return property_exists($this, 'children') ? $this->{'children'} : [];
    }

    public function getParent(): ?TrainingPeriodData
    {
        return property_exists($this, 'parent') ? $this->{'parent'} : null;
    }

    public function persist()
    {
        $modelClass = static::getModelClass();
        $data = $this->getModelData();
        $data = array_merge($data, [
            'type' => static::getModelType(),
            'parent_id' => $this->getParent()?->getIdentity()?->id,
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
                $child->persist();
            }
        }
    }
}
