<?php

namespace App\Filament\Resources\AthleteMetricTypes\Pages;

use App\Filament\Actions\CreateAction;
use App\Filament\Resources\AthleteMetricTypes\AthleteMetricTypesResource;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Builder;

class ManageAthleteMetricTypes extends ManageRecords
{
    protected static string $resource = AthleteMetricTypesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()->where('model_base', 'user');
    }
}
