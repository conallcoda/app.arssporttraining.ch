<?php

namespace App\Filament\Resources\Training;

use App\Filament\Extensions\ConfigurableResource;
use App\Filament\Resources\Training\Schemas\TrainingSessionCategoryForm;
use App\Filament\Resources\Training\Tables\TrainingSessionCategoriesTable;
use App\Models\Training\TrainingSessionCategory;

class TrainingSessionCategoryResource extends ConfigurableResource
{
    protected static function configure(): array
    {
        return [
            'model' => TrainingSessionCategory::class,
            'navigationGroup' => 'Training',
            'navigationIcon' => 'lucide-tag',
            'navigationLabel' => 'Session Categories',
            'modelLabel' => 'Session Category',
            'pluralModelLabel' => 'Session Categories',
            'breadcrumb' => 'Session Categories',
            'navigationSort' => 1,
            'form' => TrainingSessionCategoryForm::class,
            'table' => TrainingSessionCategoriesTable::class,
            'pages' => [
                'index' => [],
                'create' => true,
                'edit' => true,
            ],
        ];
    }
}
