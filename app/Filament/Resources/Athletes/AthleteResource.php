<?php

namespace App\Filament\Resources\Athletes;

use App\Filament\Extensions\ConfigurableResource;
use App\Filament\Resources\Athletes\RelationManagers\MetricsRelationManager;
use App\Filament\Resources\Athletes\Schemas\AthleteForm;
use App\Models\Users\Types\Athlete;
use Filament\Schemas\Schema;

class AthleteResource extends ConfigurableResource
{
    protected static function configure(): array
    {
        return [
            'model' => Athlete::class,
            'navigationGroup' => 'Athletes',
            'navigationIcon' => 'lucide-user',
            'navigationLabel' => 'Athletes',
            'modelLabel' => 'Athlete',
            'pluralModelLabel' => 'Athletes',
            'breadcrumb' => 'Athletes',
            'navigationSort' => 1,
            'form' => AthleteForm::class,
            'pages' => [
                'index' => [],
                'create' => true,
                'edit' => true,
            ],
        ];
    }

    protected static function tableConfig(): ?array
    {
        return [
            'columns' => [
                'forename' => [
                    'type' => \Filament\Tables\Columns\TextColumn::class,
                    'searchable' => true,
                    'sortable' => true,
                ],
                'surname' => [
                    'type' => \Filament\Tables\Columns\TextColumn::class,
                    'searchable' => true,
                    'sortable' => true,
                ],
                'email' => [
                    'type' => \Filament\Tables\Columns\TextColumn::class,
                    'searchable' => true,
                    'sortable' => true,
                    'copyable' => true,
                ],
                'phone' => [
                    'type' => \Filament\Tables\Columns\TextColumn::class,
                    'searchable' => true,
                    'sortable' => true,
                    'toggleable' => true,
                    'copyable' => true,
                ],
            ],
            'default_sort' => 'surname',
        ];
    }

    public static function getRelations(): array
    {
        return [
            MetricsRelationManager::class,
        ];
    }
}
