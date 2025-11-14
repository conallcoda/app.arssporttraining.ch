<?php

namespace App\Filament\Resources\AthleteGroups;

use App\Filament\Resources\AthleteGroups\Pages\CreateAthleteGroup;
use App\Filament\Resources\AthleteGroups\Pages\EditAthleteGroup;
use App\Filament\Resources\AthleteGroups\Pages\ListAthleteGroups;
use App\Filament\Resources\AthleteGroups\Schemas\AthleteGroupForm;
use App\Filament\Resources\AthleteGroups\Tables\AthleteGroupsTable;
use App\Models\Users\Groups\AthleteGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class AthleteGroupResource extends Resource
{
    protected static ?string $model = AthleteGroup::class;

    protected static UnitEnum|string|null $navigationGroup = 'Athletes';

    protected static string|BackedEnum|null $navigationIcon = 'lucide-users';

    protected static ?string $navigationLabel = 'Groups';

    protected static ?string $modelLabel = 'Group';

    protected static ?string $pluralModelLabel = 'Groups';

    protected static ?string $breadcrumb = 'Groups';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Schema $schema): Schema
    {
        return AthleteGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AthleteGroupsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAthleteGroups::route('/'),
            'create' => CreateAthleteGroup::route('/create'),
            'edit' => EditAthleteGroup::route('/{record}/edit'),
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
