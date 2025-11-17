<?php

namespace App\Filament\Resources\Athletes;

use App\Filament\Extensions\ConfigurableResource;
use App\Filament\Resources\Athletes\Schemas\AthleteCustomMetricsForm;
use App\Filament\Resources\Athletes\Tables\AthleteCustomMetricsTable;
use App\Models\Metrics\MetricType;

class AthleteCustomMetricsResource extends ConfigurableResource
{
    protected static function configure(): array
    {
        return [
            'model' => MetricType::class,
            'navigationGroup' => 'Athletes',
            'navigationIcon' => 'lucide-ruler',
            'navigationLabel' => 'Custom Metrics',
            'modelLabel' => 'Custom Metric',
            'pluralModelLabel' => 'Custom Metrics',
            'breadcrumb' => 'Athlete Custom Metrics',
            'navigationSort' => 3,
            'form' => AthleteCustomMetricsForm::class,
            'table' => AthleteCustomMetricsTable::class,
            'pages' => [
                'index' => [],
                'create' => true,
                'edit' => true,
            ],
        ];
    }
}
