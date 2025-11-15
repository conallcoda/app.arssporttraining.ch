<?php

namespace App\Filament\Resources\Exercises;

use App\Filament\Extensions\ConfigurableResource;
use App\Filament\Resources\Exercises\Schemas\ExerciseEquipmentForm;
use App\Filament\Resources\Exercises\Tables\ExerciseEquipmentTable;
use App\Models\Exercise\ExerciseEquipment;

class ExerciseEquipmentResource extends ConfigurableResource
{
    protected static function configure(): array
    {
        return [
            'model' => ExerciseEquipment::class,
            'navigationGroup' => 'Exercises',
            'navigationIcon' => 'lucide-weight',
            'navigationLabel' => 'Equipment',
            'modelLabel' => 'Equipment',
            'pluralModelLabel' => 'Equipment',
            'breadcrumb' => 'Equipment',
            'navigationSort' => 2,
            'form' => ExerciseEquipmentForm::class,
            'table' => ExerciseEquipmentTable::class,
            'pages' => [
                'index' => [],
                'create' => true,
                'edit' => true,
            ],
        ];
    }
}
