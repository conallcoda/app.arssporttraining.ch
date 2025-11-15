<?php

namespace App\Filament\Resources\Athletes\Schemas;

use App\Models\Metrics\MetricType;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AthleteMetricForm
{
    public static function configure(Schema $schema): Schema
    {
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

                        $labels = [
                            'boolean' => 'Boolean',
                            'duration' => 'Duration',
                            'height' => 'Height',
                            'number' => 'Number',
                            'one_rep_max' => 'One Rep Max',
                            'percentage' => 'Percentage',
                            'time_under_tension' => 'Time Under Tension',
                            'weight' => 'Weight',
                        ];

                        $options = [];
                        foreach ($allowedTypes as $type) {
                            $options[$type] = $labels[$type] ?? ucwords(str_replace('_', ' ', $type));
                        }

                        return $options;
                    })
                    ->native(false),

                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }
}
