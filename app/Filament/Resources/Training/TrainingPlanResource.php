<?php

namespace App\Filament\Resources\Training;

use App\Filament\Extensions\ConfigurableResource;
use App\Filament\Extensions\ConfigurableListRecords;
use App\Filament\Extensions\ConfigurableCreateRecord;
use App\Filament\Resources\Training\Pages\EditTrainingPlan;
use App\Filament\Resources\Training\Schemas\TrainingPlanForm;
use App\Models\Training\Periods\TrainingSeason;
use Illuminate\Database\Eloquent\Builder;

class TrainingPlanResource extends ConfigurableResource
{
    protected static function configure(): array
    {
        return [
            'model' => TrainingSeason::class,
            'navigationGroup' => 'Training',
            'navigationIcon' => 'lucide-calendar',
            'navigationLabel' => 'Training Plans',
            'modelLabel' => 'Training Plan',
            'pluralModelLabel' => 'Training Plans',
            'breadcrumb' => 'Training Plans',
            'navigationSort' => 1,
            'form' => TrainingPlanForm::class,
            'pages' => [
                'index' => [],
                'create' => true,
                'edit' => true,
            ],
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ConfigurableListRecords::configure([
                'resource' => static::class,
            ])::route('/'),
            'create' => ConfigurableCreateRecord::configure([
                'resource' => static::class,
            ])::route('/create'),
            'edit' => EditTrainingPlan::route('/{record}/edit'),
        ];
    }


    protected static function tableConfig(): ?array
    {
        return [
            'columns' => [
                'name' => [
                    'type' => \Filament\Tables\Columns\TextColumn::class,
                    'searchable' => true,
                    'sortable' => true,
                ],
                'created_at' => [
                    'type' => \Filament\Tables\Columns\TextColumn::class,
                    'dateTime' => true,
                    'sortable' => true,
                    'toggleable' => true,
                ],
            ],
            'default_sort' => 'sequence',
        ];
    }
}
