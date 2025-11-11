<?php

namespace App\Filament\Resources\Exercise\ExerciseEquipment;

use App\Filament\Clusters\Exercises\ExercisesCluster;
use App\Filament\Resources\Exercise\ExerciseEquipment\Pages\CreateExerciseEquipment;
use App\Filament\Resources\Exercise\ExerciseEquipment\Pages\EditExerciseEquipment;
use App\Filament\Resources\Exercise\ExerciseEquipment\Pages\ListExerciseEquipment;
use App\Filament\Resources\Exercise\ExerciseEquipment\Schemas\ExerciseEquipmentForm;
use App\Filament\Resources\Exercise\ExerciseEquipment\Tables\ExerciseEquipmentTable;
use App\Models\Exercise\ExerciseEquipment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ExerciseEquipmentResource extends Resource
{
    protected static ?string $model = ExerciseEquipment::class;

    protected static ?string $cluster = ExercisesCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $navigationLabel = 'Equipment';

    protected static ?string $modelLabel = 'Equipment';

    protected static ?string $pluralModelLabel = 'Equipment';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return ExerciseEquipmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExerciseEquipmentTable::configure($table);
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
            'index' => ListExerciseEquipment::route('/'),
            'create' => CreateExerciseEquipment::route('/create'),
            'edit' => EditExerciseEquipment::route('/{record}/edit'),
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
