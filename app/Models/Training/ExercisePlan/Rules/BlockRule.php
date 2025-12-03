<?php

namespace App\Models\Training\ExercisePlan\Rules;

use App\Data\AbstractData;
use App\Models\Training\ExercisePlan\ExerciseBlock;
use Illuminate\Support\Str;

abstract class BlockRule extends AbstractData
{
    abstract public function apply(ExerciseBlock $block): ExerciseBlock;

    public function title(): string
    {
        $className = class_basename($this);

        return ucfirst(strtolower(Str::headline($className)));
    }
}
