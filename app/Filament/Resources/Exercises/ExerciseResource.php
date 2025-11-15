<?php

namespace App\Filament\Resources\Exercises;

use App\Filament\Extensions\ConfigurableResource;
use App\Filament\Extensions\List\ChildTypeTabs;
use App\Filament\Resources\Exercises\Schemas\ExerciseForm;
use App\Filament\Resources\Exercises\Tables\ExercisesTable;
use App\Models\Exercise\Exercise;

class ExerciseResource extends ConfigurableResource
{
    protected static function configure(): array
    {
        return [
            'model' => Exercise::class,
            'navigationGroup' => 'Exercises',
            'navigationSort' => 0,
            'navigationIcon' => 'lucide-dumbbell',
            'form' => ExerciseForm::class,
            'table' => ExercisesTable::class,
            'pages' => [
                'index' => [
                    'tabs' => ChildTypeTabs::configure(Exercise::class),
                ],
                'create' => true,
                'edit' => true,
            ],
        ];
    }
}
