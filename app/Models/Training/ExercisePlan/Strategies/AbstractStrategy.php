<?php

namespace App\Models\Training\ExercisePlan\Strategies;

use App\Data\AbstractData;
use App\Data\Form\FluxFieldset;

abstract class AbstractStrategy extends AbstractData
{
    abstract public static function actions(): array;

    public static function configure(array $config): array
    {
        $actions = static::actions();

        return array_map(fn ($action) => $action::from($config[$action::getType()] ?? []), $actions);
    }

    public static function getConfigureForm(): array
    {
        $actions = static::actions();
        $fields = [];

        foreach ($actions as $action) {
            $actionFields = $action::getFields();

            if (count($actionFields) >= 1) {
                $fields[$action::getType()] = FluxFieldset::make($action::getTitle())
                    ->schema($actionFields);
            }
        }

        return $fields;
    }

    public static function getDefaultConfig(): array
    {
        $config = [];

        foreach (static::actions() as $action) {
            $actionFields = $action::getFields();
            if (empty($actionFields)) {
                continue;
            }

            $instance = new $action;
            $reflection = new \ReflectionClass($action);
            $constructor = $reflection->getConstructor();

            if (! $constructor) {
                continue;
            }

            $actionConfig = [];
            foreach ($constructor->getParameters() as $param) {
                $name = $param->getName();
                if ($param->isDefaultValueAvailable()) {
                    $actionConfig[$name] = $param->getDefaultValue();
                } elseif ($reflection->hasProperty($name)) {
                    $prop = $reflection->getProperty($name);
                    $prop->setAccessible(true);
                    $actionConfig[$name] = $prop->getValue($instance);
                }
            }

            if (! empty($actionConfig)) {
                $config[$action::getType()] = $actionConfig;
            }
        }

        return $config;
    }
}
