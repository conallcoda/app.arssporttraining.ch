<?php

namespace App\Data\MetricTypes\Athlete;

use App\Data\MetricTypes\AbstractMetricType;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

class OneRepMax extends AbstractMetricType
{
    public static function defaults(): array
    {
        return [
            'multiplier' => 1,
        ];
    }

    public function __construct(public ?float $multiplier = null)
    {
        $this->multiplier = $multiplier ?? static::defaults()['multiplier'];
    }

    public static function unit($short = true): ?string
    {
        return $short ? 'kg' : 'kilograms';
    }

    public static function conversionTable(): array
    {
        return [
            1 => 1,
            2 => 0.96,
            3 => 0.94,
            4 => 0.91,
            5 => 0.88,
            6 => 0.86,
            7 => 0.83,
            8 => 0.8,
            9 => 0.77,
            10 => 0.74,
            11 => 0.71,
            12 => 0.67,
            13 => 0.65,
            14 => 0.63,
            15 => 0.62,
        ];
    }

    public static function rules(array $actual = []): array
    {
        return [
            'numeric',
            'min:0',
        ];
    }

    public  function recordFields($get = null): array
    {
        $multiplier = $this->multiplier;
        $metricTypeId = $get ? $get('metric_type_id') : null;

        $calculateValue = function (Get $get, $set) use ($multiplier) {
            $reps = $get('extra.reps');
            $weight = $get('extra.weight');

            if (!is_numeric($reps) || !is_numeric($weight)) {
                $set('value.value', null);
                return;
            }

            $conversionTable = static::conversionTable();
            $conversion = $conversionTable[(int)$reps] ?? null;

            if ($conversion === null || $conversion == 0) {
                $set('value.value', null);
                return;
            }

            $calculated = $weight / $conversion;
            $multiplied = $calculated * $multiplier;
            $rounded = floor($multiplied);


            $set('value.value', $rounded);
        };

        return [
            TextInput::make('extra.weight')
                ->label('Weight')
                ->required()
                ->numeric()
                ->step(1)
                ->minValue(1)
                ->maxValue(500)
                ->live(onBlur: true)
                ->afterStateUpdated($calculateValue)
                ->afterStateHydrated($calculateValue),
            TextInput::make('extra.reps')
                ->label('Reps')
                ->required()
                ->numeric()
                ->minValue(1)
                ->step(1)
                ->maxValue(15)
                ->live(onBlur: true)
                ->afterStateUpdated($calculateValue)
                ->afterStateHydrated($calculateValue),
            TextInput::make('extra.multiplier')
                ->default($multiplier)
                ->hidden()
                ->dehydrated(),

            TextInput::make('value.value')
                ->label('Calculated 1RM')
                ->readOnly()
                ->numeric()
                ->suffix(static::unit(short: true))
                ->visible(
                    fn(Get $get) =>
                    is_numeric($get('extra.reps')) &&
                        is_numeric($get('extra.weight')) &&
                        $get('extra.reps') >= 1 &&
                        $get('extra.reps') <= 15 &&
                        $get('extra.weight') > 0
                )
                ->dehydrated()
        ];
    }

    public static function createFields(): array
    {
        return [
            TextInput::make('config.multiplier')
                ->default(0)
                ->numeric()
                ->required()
        ];
    }
}
