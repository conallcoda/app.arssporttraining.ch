<?php

namespace App\Filament\Resources\Exercise\ExerciseEquipment\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ExerciseEquipmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
            ]);
    }
}
