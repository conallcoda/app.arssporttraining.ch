<?php

namespace App\Models\Training\ExercisePlan\Actions;

use App\Data\AbstractData;
use App\Models\Contracts\HasForms;
use App\Models\Training\ExercisePlan\ExerciseBlock;
use Illuminate\Support\Str;

abstract class BlockAction extends AbstractData implements HasForms
{
    abstract public function apply(ExerciseBlock $block): BlockResult;

    public static function getFields(): array
    {
        return [];
    }

    public static function getType(): string
    {
        return Str::snake(class_basename(static::class));
    }

    protected function result(ExerciseBlock $previous, ExerciseBlock $current): BlockResult
    {
        return new BlockResult(
            action: $this,
            previous: $previous,
            current: $current,
        );
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
