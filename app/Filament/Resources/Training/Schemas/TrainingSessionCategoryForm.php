<?php

namespace App\Filament\Resources\Training\Schemas;

use App\Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TrainingSessionCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Category Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        ColorPicker::make('text_color')
                            ->label('Text Color'),

                        ColorPicker::make('background_color')
                            ->label('Background Color'),
                    ]),
            ]);
    }
}
