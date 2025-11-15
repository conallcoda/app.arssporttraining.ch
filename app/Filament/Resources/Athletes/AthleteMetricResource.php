<?php

namespace App\Filament\Resources\Athletes;

use App\Filament\Extensions\ConfigurableResource;
use App\Filament\Resources\Athletes\Schemas\AthleteMetricForm;
use App\Filament\Resources\Athletes\Tables\AthleteMetricsTable;
use App\Models\Metrics\MetricType;

class AthleteMetricResource extends ConfigurableResource
{
    protected static function configure(): array
    {
        return [
            'model' => MetricType::class,
            'navigationGroup' => 'Athletes',
            'navigationIcon' => 'lucide-ruler',
            'navigationLabel' => 'Metrics',
            'modelLabel' => 'Metric',
            'pluralModelLabel' => 'Metrics',
            'breadcrumb' => 'Athlete Metrics',
            'navigationSort' => 3,
            'form' => AthleteMetricForm::class,
            'table' => AthleteMetricsTable::class,
            'pages' => [
                'index' => [],
                'create' => true,
                'edit' => true,
            ],
        ];
    }
}
