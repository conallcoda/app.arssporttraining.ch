<?php

namespace App\Filament\Resources\Training\Pages;

use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EditTrainingPlan extends EditRecord
{
    protected static string $resource = \App\Filament\Resources\Training\TrainingPlanResource::class;

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Hello World')
                    ->description('This is a custom edit page for training plans')
                    ->schema([]),
            ]);
    }
}
