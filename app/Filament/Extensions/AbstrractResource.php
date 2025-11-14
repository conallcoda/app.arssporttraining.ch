<?php

namespace App\Filament\Extensions;

use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

abstract class AbstractResource extends Resource
{
    abstract protected static function setUp(): array;

    public static function getModel(): string
    {
        return static::setUp()['model'];
    }

    public static function getNavigationGroup(): ?string
    {
        return static::setUp()['navigationGroup'] ?? null;
    }

    public static function getNavigationIcon(): ?string
    {
        return static::setUp()['navigationIcon'] ?? null;
    }

    public static function getNavigationLabel(): string
    {
        return static::setUp()['navigationLabel'] ?? null;
    }

    public static function getModelLabel(): string
    {
        return static::setUp()['modelLabel'] ?? parent::getModelLabel();
    }

    public static function getPluralModelLabel(): string
    {
        return static::setUp()['pluralModelLabel'] ?? parent::getPluralModelLabel();
    }

    public static function getNavigationSort(): ?int
    {
        return static::setUp()['navigationSort'] ?? null;
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
