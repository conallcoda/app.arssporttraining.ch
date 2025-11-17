<?php

namespace App\Filament\Resources\Athletes\Schemas;

use App\Models\Users\Types\Athlete;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AthleteCustomMetricsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->schema([
                        Hidden::make('scope')
                            ->default('athlete'),

                        Select::make('type')
                            ->options([
                                'weight' => 'Weight',
                                'distance' => 'Distance',
                                'time' => 'Time',
                                'percentage' => 'Percentage',
                                'number' => 'Number',
                                'one_rep_max' => 'One Rep Max',
                            ])
                            ->required()
                            ->native(false)
                            ->live()
                            ->columnSpanFull(),

                        TextInput::make('label')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($set, ?string $state, ?string $old) {
                                if (empty($old) || $old === Str::slug($state, '_')) {
                                    $set('name', Str::slug($state, '_'));
                                }
                            })
                            ->rules([
                                fn($get, $record) => Rule::unique('metric_types', 'label')
                                    ->where('scope', $get('scope') ?? 'athlete')
                                    ->ignore($record?->id),
                            ])
                            ->validationMessages([
                                'unique' => 'A metric type with this label already exists.',
                            ])
                            ->columnSpan(1),

                        TextInput::make('name')
                            ->label('Variable Name')
                            ->required()
                            ->maxLength(255)
                            ->rules([
                                fn($get, $record) => Rule::unique('metric_types', 'name')
                                    ->where('scope', $get('scope') ?? 'athlete')
                                    ->ignore($record?->id),
                            ])
                            ->validationMessages([
                                'unique' => 'A metric type with this variable name already exists.',
                            ])
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->columnSpan(1),

                Section::make('Config')
                    ->schema(fn($get) => self::getTypeFields($get('type')))
                    ->visible(fn($get) => filled($get('type')) && !empty(self::getTypeFields($get('type'))))
                    ->columnSpan(1),
            ]);
    }

    protected static function getTypeFields(?string $type): array
    {
        if (!$type) {
            return [];
        }

        try {
            $className = Athlete::getMetricTypeModel($type);
        } catch (\Exception $e) {
            return [];
        }

        $fields = $className::createFields();

        return $fields;
    }
}
