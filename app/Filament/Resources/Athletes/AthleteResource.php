<?php

namespace App\Filament\Resources\Athletes;

use App\Filament\Resources\Athletes\Pages\CreateAthlete;
use App\Filament\Resources\Athletes\Pages\EditAthlete;
use App\Filament\Resources\Athletes\Pages\ListAthletes;
use App\Filament\Resources\Athletes\Schemas\AthleteForm;
use App\Filament\Resources\Athletes\Tables\AthletesTable;
use App\Models\Users\Types\Athlete;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AthleteResource extends Resource
{
    protected static ?string $model = Athlete::class;

    protected static UnitEnum|string|null $navigationGroup = 'Athletes';

    protected static string|BackedEnum|null $navigationIcon = 'lucide-user';

    protected static ?string $navigationLabel = 'Athletes';

    protected static ?string $modelLabel = 'Athlete';

    protected static ?string $pluralModelLabel = 'Athletes';

    protected static ?string $breadcrumb = 'Athletes';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Schema $schema): Schema
    {
        return AthleteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AthletesTable::configure($table);
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
            'index' => ListAthletes::route('/'),
            'create' => CreateAthlete::route('/create'),
            'edit' => EditAthlete::route('/{record}/edit'),
        ];
    }
}
