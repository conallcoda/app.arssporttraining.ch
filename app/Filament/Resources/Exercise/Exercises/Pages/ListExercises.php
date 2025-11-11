<?php

namespace App\Filament\Resources\Exercise\Exercises\Pages;

use App\Filament\Pages\AbstractListRecords;
use App\Filament\Resources\Exercise\Exercises\ExerciseResource;
use App\Models\Exercise\Types\StrengthExercise;
use App\Models\Exercise\Types\PlyometricExercise;
use App\Models\Exercise\Types\StretchingExercise;
use App\Models\Exercise\Types\CardioExercise;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListExercises extends AbstractListRecords
{
    protected static string $resource = ExerciseResource::class;

    public function getBreadcrumbs(): array
    {
        return [
            ExerciseResource::getUrl() => 'Exercises',
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->badge(fn() => \App\Models\Exercise\Exercise::count()),
            'strength' => Tab::make('Strength')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('type', 'strength'))
                ->badge(fn() => StrengthExercise::count()),
            'plyometric' => Tab::make('Plyometric')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('type', 'plyometric'))
                ->badge(fn() => PlyometricExercise::count()),
            'stretching' => Tab::make('Stretching')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('type', 'stretching'))
                ->badge(fn() => StretchingExercise::count()),
            'cardio' => Tab::make('Cardio')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('type', 'cardio'))
                ->badge(fn() => CardioExercise::count()),
        ];
    }
}
