<?php

namespace App\Filament\Resources\Exercises;

use App\Filament\Extensions\ConfigurableResource;
use App\Filament\Resources\Exercises\Tables\ExerciseMusclesTable;
use App\Filament\Resources\Exercises\Schemas\ExerciseMuscleForm;
use App\Models\Exercise\ExerciseMuscle;

class ExerciseMuscleResource extends ConfigurableResource
{
    protected static function configure(): array
    {
        return [
            'model' => ExerciseMuscle::class,
            'navigationGroup' => 'Exercises',
            'navigationIcon' => 'lucide-biceps-flexed',
            'navigationLabel' => 'Muscle Groups',
            'modelLabel' => 'Muscle Group',
            'pluralModelLabel' => 'Muscle Groups',
            'breadcrumb' => 'Muscle Groups',
            'navigationSort' => 3,
            'form' => ExerciseMuscleForm::class,
            'table' => ExerciseMusclesTable::class,
            'pages' => [
                'index' => [],
                'create' => true,
                'edit' => true,
            ],
        ];
    }
}
