<?php

namespace App\Filament\Resources\Exercise\ExerciseMuscles;

use App\Filament\Clusters\Exercises\ExercisesCluster;
use App\Filament\Resources\Exercise\ExerciseMuscles\Pages\CreateExerciseMuscle;
use App\Filament\Resources\Exercise\ExerciseMuscles\Pages\EditExerciseMuscle;
use App\Filament\Resources\Exercise\ExerciseMuscles\Pages\ListExerciseMuscles;
use App\Filament\Resources\Exercise\ExerciseMuscles\Schemas\ExerciseMuscleForm;
use App\Filament\Resources\Exercise\ExerciseMuscles\Tables\ExerciseMusclesTable;
use App\Models\Exercise\ExerciseMuscle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ExerciseMuscleResource extends Resource
{
    protected static ?string $model = ExerciseMuscle::class;

    protected static UnitEnum|string|null $navigationGroup = 'Exercises';

    protected static string|BackedEnum|null $navigationIcon = 'lucide-biceps-flexed';

    protected static ?string $navigationLabel = 'Muscle Groups';

    protected static ?string $modelLabel = 'Muscle Group';

    protected static ?string $pluralModelLabel = 'Muscle Groups';

    protected static ?string $breadcrumb = 'Muscle Groups';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Schema $schema): Schema
    {
        return ExerciseMuscleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExerciseMusclesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExerciseMuscles::route('/'),
            'create' => CreateExerciseMuscle::route('/create'),
            'edit' => EditExerciseMuscle::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
