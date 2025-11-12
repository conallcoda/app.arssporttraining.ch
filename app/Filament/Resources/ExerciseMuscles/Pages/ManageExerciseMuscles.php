<?php

namespace App\Filament\Resources\ExerciseMuscles\Pages;

use App\Filament\Actions\CreateAction;
use App\Filament\Resources\ExerciseMuscles\ExerciseMuscleResource;
use Filament\Resources\Pages\ManageRecords;

class ManageExerciseMuscles extends ManageRecords
{
    protected static string $resource = ExerciseMuscleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
