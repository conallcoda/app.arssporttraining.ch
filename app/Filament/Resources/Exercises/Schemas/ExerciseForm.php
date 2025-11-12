<?php

namespace App\Filament\Resources\Exercises\Schemas;

use App\Models\Exercise\Level;
use App\Models\Exercise\Mechanic;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ExerciseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->schema([
                        ToggleButtons::make('type')
                            ->label('Type')
                            ->options([
                                'strength' => 'strength',
                                'plyometric' => 'plyometric',
                                'stretching' => 'stretching',
                                'cardio' => 'cardio',
                            ])
                            ->required()
                            ->inline()
                            ->default('strength')
                            ->columnSpanFull(),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Classification')
                    ->schema([
                        ToggleButtons::make('level')
                            ->options(Level::class)
                            ->inline(),
                        ToggleButtons::make('mechanic')
                            ->options(Mechanic::class)
                            ->inline(),
                        Select::make('equipment')
                            ->label('Equipment')
                            ->multiple()
                            ->relationship('equipment', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->native(false),
                    ])
                    ->columns(2),

                Section::make('Muscle Groups')
                    ->schema([
                        Select::make('primaryMuscles')
                            ->label('Primary')
                            ->multiple()
                            ->relationship('primaryMuscles', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->native(false),
                        Select::make('secondaryMuscles')
                            ->label('Secondary')
                            ->multiple()
                            ->relationship('secondaryMuscles', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->native(false),
                    ])
                    ->columns(2),

                Section::make('Instructions')
                    ->schema([
                        Repeater::make('instructions')
                            ->schema([
                                Textarea::make('step')
                                    ->label('')
                                    ->rows(2)
                                    ->required(),
                            ])
                            ->simple(
                                TextInput::make('step')
                                    ->label('Step')
                            )
                            ->addActionLabel('Add instruction step')
                            ->collapsible()
                            ->reorderable()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
