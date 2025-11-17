<?php

namespace App\Data\MetricTypes;

use App\Data\AbstractData;
use Filament\Forms;

abstract class AbstractMetricType extends AbstractData implements MetricType
{

    public static function createFields(): array
    {
        return [];
    }

    public static function recordFields(): array
    {
        return [
            Forms\Components\TextInput::make('value')
                ->label('Value')
                ->required()
                ->columnSpanFull()
                ->suffix(function () {
                    return static::unit(true);
                })
                ->rules(function () {
                    $rules = array_merge(['required'], static::rules());
                    return $rules;
                })
        ];
    }
}
