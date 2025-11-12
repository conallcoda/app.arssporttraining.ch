<?php

namespace App\Filament\Resources\AthleteGroups\Schemas;

use App\Models\Users\Types\Athlete;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AthleteGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->columns(1),

                Section::make('Members')
                    ->schema([
                        Select::make('members')
                            ->relationship(
                                name: 'members',
                                modifyQueryUsing: fn($query) => $query
                                    ->where('type', 'athlete')
                                    ->orderBy('forename')
                                    ->orderBy('surname')
                            )
                            ->getOptionLabelFromRecordUsing(fn(Athlete $record) => "{$record->name}")
                            ->multiple()
                            ->native(false)
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(1),
            ]);
    }
}
