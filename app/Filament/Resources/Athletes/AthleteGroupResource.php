<?php

namespace App\Filament\Resources\Athletes;

use App\Filament\Extensions\ConfigurableResource;
use App\Filament\Resources\Athletes\Schemas\AthleteGroupForm;
use App\Filament\Resources\Athletes\Tables\AthleteGroupsTable;
use App\Models\Users\Groups\AthleteGroup;

class AthleteGroupResource extends ConfigurableResource
{
    protected static function configure(): array
    {
        return [
            'model' => AthleteGroup::class,
            'navigationGroup' => 'Athletes',
            'navigationIcon' => 'lucide-users',
            'navigationLabel' => 'Groups',
            'modelLabel' => 'Group',
            'pluralModelLabel' => 'Groups',
            'breadcrumb' => 'Athlete Groups',
            'navigationSort' => 2,
            'form' => AthleteGroupForm::class,
            'table' => AthleteGroupsTable::class,
            'pages' => [
                'index' => [],
                'create' => true,
                'edit' => true,
            ],
        ];
    }
}
