<?php

namespace App\Filament\Resources\Athletes\Schemas;

use App\Models\Users\User;
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
                                modifyQueryUsing: fn ($query) => $query
                                    ->orderBy('forename')
                                    ->orderBy('surname')
                            )
                            ->getOptionLabelFromRecordUsing(fn (User $record) => "{$record->name}")
                            ->multiple()
                            ->native(false)
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(1),
            ]);
    }
}
