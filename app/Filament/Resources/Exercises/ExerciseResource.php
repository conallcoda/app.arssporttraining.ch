<?php

namespace App\Filament\Resources\Exercises;

use App\Filament\Extensions\ConfigurableResource;
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
                    'tabs' => [
                        'all' => [
                            'label' => 'All',
                            'badge' => ['model' => Exercise::class],
                        ],
                        'strength' => [
                            'label' => 'Strength',
                            'query' => ['type' => 'strength'],
                            'badge' => ['model' => Exercise::class, 'query' => ['type' => 'strength']],
                        ],
                        'plyometric' => [
                            'label' => 'Plyometric',
                            'query' => ['type' => 'plyometric'],
                            'badge' => ['model' => Exercise::class, 'query' => ['type' => 'plyometric']],
                        ],
                        'stretching' => [
                            'label' => 'Stretching',
                            'query' => ['type' => 'stretching'],
                            'badge' => ['model' => Exercise::class, 'query' => ['type' => 'stretching']],
                        ],
                        'cardio' => [
                            'label' => 'Cardio',
                            'query' => ['type' => 'cardio'],
                            'badge' => ['model' => Exercise::class, 'query' => ['type' => 'cardio']],
                        ],
                    ],
                ],
                'create' => true,
                'edit' => true,
            ],
        ];
    }
}
