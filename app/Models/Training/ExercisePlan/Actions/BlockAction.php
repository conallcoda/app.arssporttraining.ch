<?php

namespace App\Models\Training\ExercisePlan\Actions;

use App\Data\AbstractData;
use App\Models\Training\ExercisePlan\ExerciseBlock;
use Illuminate\Support\Str;

abstract class BlockAction extends AbstractData
{
    abstract public function apply(ExerciseBlock $block): BlockResult;

    abstract public function getType(): string;

    public function title(): string
    {
        $className = class_basename($this);

        return ucfirst(strtolower(Str::headline($className)));
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'class' => static::class,
            'type' => $this->getType(),
        ]);
    }
}
