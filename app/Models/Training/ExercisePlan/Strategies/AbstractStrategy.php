<?php

namespace App\Models\Training\ExercisePlan\Strategies;

use App\Data\AbstractData;

abstract class AbstractStrategy extends AbstractData
{
    abstract public static function actions(array $config): array;

    protected static function mapActions(array $actions, array $config): array
    {
        return array_map(fn ($action) => $action::from($config[$action::getType()] ?? []), $actions);
    }
}
