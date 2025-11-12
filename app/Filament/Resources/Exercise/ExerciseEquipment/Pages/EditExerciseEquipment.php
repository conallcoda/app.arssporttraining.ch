<?php

namespace App\Filament\Resources\Exercise\ExerciseEquipment\Pages;

use App\Filament\Pages\AbstractEditRecord;
use App\Filament\Resources\Exercise\ExerciseEquipment\ExerciseEquipmentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;

class EditExerciseEquipment extends AbstractEditRecord
{
    protected static string $resource = ExerciseEquipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getCancelFormAction()->formId('form'),
            $this->getSaveFormAction()->formId('form'),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            DeleteAction::make()
                ->extraAttributes(['class' => 'ml-auto']),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
