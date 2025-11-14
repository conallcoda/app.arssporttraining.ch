<?php

namespace App\Filament\Resources\Exercises;

use App\Filament\Extensions\ConfigurableResource;
use App\Models\Exercise\ExerciseEquipment;
use Filament\Forms\Components;
use Filament\Tables\Columns;
use Filament\Tables\Filters;

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
                'Equipment Information' => [
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
