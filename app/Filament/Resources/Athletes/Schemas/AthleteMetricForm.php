<?php

namespace App\Filament\Resources\Athletes\Schemas;

use App\Models\Metrics\MetricType;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class AthleteMetricForm
{
    public static function configure(Schema $schema): Schema
    {
        $precisionDefaults = [
            'boolean' => false,
            'duration' => 0,
            'height' => 2,
            'number' => 2,
            'one_rep_max' => 0,
            'percentage' => 2,
            'time_under_tension' => false,
            'weight' => 1
        ];

        return $schema
            ->components([
                Hidden::make('model_base')
                    ->default('user'),

                Hidden::make('model_sub')
                    ->default('athlete'),

                Select::make('type')
                    ->required()
                    ->options(function () {
                        $allowedTypes = MetricType::getAllowedMetricTypesFor('user', 'athlete');
                        $options = [];
                        foreach ($allowedTypes as $type) {
                            $options[$type] = Str::title(str_replace('_', ' ', $type));
                        }
                        return $options;
                    })
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function (Set $set, $state) use ($precisionDefaults) {
                        $precision = $precisionDefaults[$state] ?? 0;
                        if ($precision !== false) {
                            $set('extra.precision', $precision);
                        }
                    }),

                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('extra.precision')
                    ->label('Precision')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(10)
                    ->default(function (Get $get) use ($precisionDefaults) {
                        return $precisionDefaults[$get('type')] ?? 0;
                    })
                    ->afterStateHydrated(function (TextInput $component, $state, Get $get) use ($precisionDefaults) {
                        if ($state === null || $state === '') {
                            $type = $get('type');
                            if ($type && isset($precisionDefaults[$type]) && $precisionDefaults[$type] !== false) {
                                $component->state($precisionDefaults[$type]);
                            }
                        }
                    })
                    ->hidden(function (Get $get) use ($precisionDefaults) {
                        $type = $get('type');
                        if (!$type) {
                            return true;
                        }
                        return ($precisionDefaults[$type] ?? 0) === false;
                    })
                    ->disabled(function (Get $get) use ($precisionDefaults) {
                        $type = $get('type');
                        if (!$type) {
                            return true;
                        }
                        return ($precisionDefaults[$type] ?? 0) === false;
                    })
                    ->helperText('Number of decimal places'),
            ]);
    }
}
