<?php

namespace App\Filament\Resources\Exercise\Exercises\Pages;

use App\Filament\Pages\AbstractEditRecord;
use App\Filament\Resources\Exercise\Exercises\ExerciseResource;
use App\Models\Exercise\Exercise;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Illuminate\Database\Eloquent\Model;

class EditExercise extends AbstractEditRecord
{
    protected static string $resource = ExerciseResource::class;

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

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->update($data);
        if (isset($data['type']) && $data['type'] !== $record->type) {
            return Exercise::find($record->id);
        }

        return $record;
    }
}
