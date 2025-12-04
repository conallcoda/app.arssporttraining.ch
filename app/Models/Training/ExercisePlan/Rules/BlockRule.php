<?php

namespace App\Models\Training\ExercisePlan\Rules;

use App\Data\AbstractData;
use App\Models\Contracts\HasForms;
use App\Models\Training\ExercisePlan\ExerciseBlock;
use Illuminate\Support\Str;

abstract class BlockRule extends AbstractData implements HasForms
{
    abstract public function apply(ExerciseBlock $block): ExerciseBlock;

    public static function getFields(): array
    {
        return [];
    }

    public static function getType(): string
    {
        return Str::snake(class_basename(static::class));
    }

    public function title(): string
    {
        return static::getTitle();
    }

    public static function getTitle(): string
    {
        $className = class_basename(static::class);

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
