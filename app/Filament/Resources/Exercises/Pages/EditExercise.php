<?php

namespace App\Filament\Resources\Exercises\Pages;

use App\Filament\Pages\AbstractEditRecord;
use App\Filament\Resources\Exercises\ExerciseResource;
use App\Models\Exercise\Exercise;
use Illuminate\Database\Eloquent\Model;

class EditExercise extends AbstractEditRecord
{
    protected static string $resource = ExerciseResource::class;


    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->update($data);
        if (isset($data['type']) && $data['type'] !== $record->type) {
            return Exercise::find($record->id);
        }

        return $record;
    }
}
