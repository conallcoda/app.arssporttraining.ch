<?php

namespace App\Filament\Resources\Exercises;

use App\Filament\Extensions\ConfigurableResource;
use App\Models\Exercise\ExerciseMuscle;
use Filament\Forms\Components;
use Filament\Tables\Columns;
use Filament\Tables\Filters;

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
            'pages' => [
                'index' => [],
                'create' => true,
                'edit' => true,
            ],
        ];
    }

    protected static function formConfig(): ?array
    {
        return [
            'sections' => [
                'Muscle Group Information' => [
                    'fields' => [
                        'name' => [
                            'type' => Components\TextInput::class,
                            'required' => true,
                            'max_length' => 255,
                        ],
                    ],
                ],
            ],
        ];
    }

    protected static function tableConfig(): ?array
    {
        return [
            'columns' => [
                'name' => [
                    'type' => Columns\TextColumn::class,
                    'searchable' => true,
                    'sortable' => true,
                ],
            ],
            'filters' => [
                'trashed' => [
                    'type' => Filters\TrashedFilter::class,
                ],
            ],
            'default_sort' => 'name',
        ];
    }
}
