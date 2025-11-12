<?php

namespace App\Filament\Resources\ExerciseEquipment\Pages;

use App\Filament\Actions\CreateAction;
use App\Filament\Resources\ExerciseEquipment\ExerciseEquipmentResource;
use Filament\Resources\Pages\ManageRecords;

class ManageExerciseEquipment extends ManageRecords
{
    protected static string $resource = ExerciseEquipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
