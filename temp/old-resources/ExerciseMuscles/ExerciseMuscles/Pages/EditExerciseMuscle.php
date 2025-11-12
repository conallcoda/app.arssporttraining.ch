<?php

namespace App\Filament\Resources\Exercise\ExerciseMuscles\Pages;

use App\Filament\Pages\AbstractEditRecord;
use App\Filament\Resources\Exercise\ExerciseMuscles\ExerciseMuscleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;

class EditExerciseMuscle extends AbstractEditRecord
{
    protected static string $resource = ExerciseMuscleResource::class;

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
