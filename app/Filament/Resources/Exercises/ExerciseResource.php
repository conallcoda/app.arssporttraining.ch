<?php

namespace App\Filament\Resources\Exercises;

use App\Filament\Extensions\ConfigurableResource;
use App\Filament\Extensions\List\ChildTypeTabs;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\Level;
use App\Models\Exercise\Mechanic;
use Filament\Forms\Components;
use Awcodes\BadgeableColumn\Components\BadgeableColumn;
use Filament\Tables\Columns;
use Filament\Tables\Filters;

class ExerciseResource extends ConfigurableResource
{
    protected static function configure(): array
    {
        return [
            'model' => Exercise::class,
            'navigationGroup' => 'Exercises',
            'navigationSort' => 0,
            'navigationIcon' => 'lucide-dumbbell',
            'pages' => [
                'index' => [
                    'tabs' => ChildTypeTabs::configure(Exercise::class),
                ],
                'create' => true,
                'edit' => true,
            ],
        ];
    }

    protected static function formConfig(): ?array
    {
        return [
            'sections' => [
                'Basic Information' => [
                    'columns' => 2,
                    'fields' => [
                        'type' => [
                            'type' => Components\ToggleButtons::class,
                            'label' => 'Type',
                            'options' => [
                                'strength' => 'strength',
                                'plyometric' => 'plyometric',
                                'stretching' => 'stretching',
                                'cardio' => 'cardio',
                            ],
                            'required' => true,
                            'inline' => true,
                            'default' => 'strength',
                            'column_span_full' => true,
                        ],
                        'name' => [
                            'type' => Components\TextInput::class,
                            'required' => true,
                            'max_length' => 255,
                            'column_span_full' => true,
                        ],
                    ],
                ],
                'Classification' => [
                    'columns' => 2,
                    'fields' => [
                        'level' => [
                            'type' => Components\ToggleButtons::class,
                            'options' => Level::class,
                            'inline' => true,
                        ],
                        'mechanic' => [
                            'type' => Components\ToggleButtons::class,
                            'options' => Mechanic::class,
                            'inline' => true,
                        ],
                        'equipment' => [
                            'type' => Components\Select::class,
                            'label' => 'Equipment',
                            'multiple' => true,
                            'relationship' => [
                                'name' => 'equipment',
                                'title_attribute' => 'name',
                            ],
                            'searchable' => true,
                            'preload' => true,
                            'native' => false,
                            'create_option_form' => [
                                'name' => [
                                    'type' => Components\TextInput::class,
                                    'required' => true,
                                    'max_length' => 255,
                                ],
                            ],
                        ],
                    ],
                ],
                'Muscle Groups' => [
                    'columns' => 2,
                    'fields' => [
                        'primaryMuscles' => [
                            'type' => Components\Select::class,
                            'label' => 'Primary',
                            'multiple' => true,
                            'relationship' => [
                                'name' => 'primaryMuscles',
                                'title_attribute' => 'name',
                            ],
                            'searchable' => true,
                            'preload' => true,
                            'native' => false,
                            'create_option_form' => [
                                'name' => [
                                    'type' => Components\TextInput::class,
                                    'required' => true,
                                    'max_length' => 255,
                                ],
                            ],
                        ],
                        'secondaryMuscles' => [
                            'type' => Components\Select::class,
                            'label' => 'Secondary',
                            'multiple' => true,
                            'relationship' => [
                                'name' => 'secondaryMuscles',
                                'title_attribute' => 'name',
                            ],
                            'searchable' => true,
                            'preload' => true,
                            'native' => false,
                            'create_option_form' => [
                                'name' => [
                                    'type' => Components\TextInput::class,
                                    'required' => true,
                                    'max_length' => 255,
                                ],
                            ],
                        ],
                    ],
                ],
                'Instructions' => [
                    'fields' => [
                        'instructions' => [
                            'type' => Components\Repeater::class,
                            'schema' => [
                                'step' => [
                                    'type' => Components\Textarea::class,
                                    'label' => '',
                                    'rows' => 2,
                                    'required' => true,
                                ],
                            ],
                            'simple' => [
                                'type' => Components\TextInput::class,
                                'name' => 'step',
                                'label' => 'Step',
                            ],
                            'add_action_label' => 'Add instruction step',
                            'collapsible' => true,
                            'reorderable' => true,
                            'column_span_full' => true,
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
                    'type' => BadgeableColumn::class,
                    'suffix_badges' => Exercise::getBadges(),
                    'separator' => false,
                    'searchable' => true,
                    'sortable' => true,
                ],
                'created_at' => [
                    'type' => Columns\TextColumn::class,
                    'date_time' => true,
                    'sortable' => true,
                    'toggleable' => ['isToggledHiddenByDefault' => true],
                ],
                'updated_at' => [
                    'type' => Columns\TextColumn::class,
                    'date_time' => true,
                    'sortable' => true,
                    'toggleable' => ['isToggledHiddenByDefault' => true],
                ],
                'deleted_at' => [
                    'type' => Columns\TextColumn::class,
                    'date_time' => true,
                    'sortable' => true,
                    'toggleable' => ['isToggledHiddenByDefault' => true],
                ],
            ],
            'filters' => [
                'type' => [
                    'type' => Filters\SelectFilter::class,
                    'options' => [
                        'strength' => 'strength',
                        'plyometric' => 'plyometric',
                        'stretching' => 'stretching',
                        'cardio' => 'cardio',
                    ],
                    'native' => false,
                ],
                'level' => [
                    'type' => Filters\SelectFilter::class,
                    'options' => Level::class,
                    'native' => false,
                ],
                'mechanic' => [
                    'type' => Filters\SelectFilter::class,
                    'options' => Mechanic::class,
                    'native' => false,
                ],
                'equipment' => [
                    'type' => Filters\SelectFilter::class,
                    'label' => 'Equipment',
                    'relationship' => [
                        'name' => 'equipment',
                        'title_attribute' => 'name',
                    ],
                    'searchable' => true,
                    'preload' => true,
                    'multiple' => true,
                    'native' => false,
                ],
                'trashed' => [
                    'type' => Filters\TrashedFilter::class,
                ],
            ],
            'default_sort' => 'name',
        ];
    }
}
