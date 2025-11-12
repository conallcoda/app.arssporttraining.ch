<?php

namespace App\Filament\Resources\Athletes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AthleteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->schema([
                        TextInput::make('forename')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('surname')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Section::make('Contact Information')
                    ->schema([
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('phone')
                            ->tel()
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Section::make('Address')
                    ->schema([
                        TextInput::make('extra.address.street')
                            ->label('Street')
                            ->maxLength(255),
                        TextInput::make('extra.address.city')
                            ->label('City')
                            ->maxLength(255),
                        TextInput::make('extra.address.state')
                            ->label('State/Province')
                            ->maxLength(255),
                        TextInput::make('extra.address.postcode')
                            ->label('Postcode')
                            ->maxLength(255),
                        TextInput::make('extra.address.country')
                            ->label('Country')
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Section::make('Password')
                    ->schema([
                        TextInput::make('password')
                            ->password()
                            ->required(fn(string $operation): bool => $operation === 'create')
                            ->dehydrated(fn($state) => filled($state))
                            ->minLength(8)
                            ->maxLength(255)
                            ->revealable()
                            ->helperText(fn(string $operation): ?string => $operation === 'edit' ? 'Leave blank to keep current password when editing.' : null),
                    ])
                    ->columns(1),
            ]);
    }
}
