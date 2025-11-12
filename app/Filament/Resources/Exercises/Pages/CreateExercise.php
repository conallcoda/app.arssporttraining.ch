<?php

namespace App\Filament\Resources\Exercises\Pages;

use App\Filament\Pages\AbstractCreateRecord;
use App\Filament\Resources\Exercises\ExerciseResource;
use App\Models\Exercise\Exercise;

class CreateExercise extends AbstractCreateRecord
{
    protected static string $resource = ExerciseResource::class;

    protected function handleRecordCreation(array $data): Exercise
    {
        $type = $data['type'] ?? 'strength';
        $childTypes = (new Exercise())->getChildTypes();
        $modelClass = $childTypes[$type] ?? Exercise::class;
        return $modelClass::create($data);
    }
}
