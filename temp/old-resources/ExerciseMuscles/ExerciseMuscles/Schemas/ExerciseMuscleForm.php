<?php

namespace App\Filament\Resources\Exercise\ExerciseMuscles\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ExerciseMuscleForm
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
