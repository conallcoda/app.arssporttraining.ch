<?php

namespace App\Filament\Extensions;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Table;
use App\Filament\Extensions\Actions\DeleteAction;

abstract class AbstractTable
{
    abstract public static function configure(Table $table): Table;

    protected static function getDefaultRecordActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make()
        ];
    }

    protected static function getDefaultToolbarActions(): array
    {
        return [
            BulkActionGroup::make([
                DeleteBulkAction::make(),
                ForceDeleteBulkAction::make(),
                RestoreBulkAction::make(),
            ]),
        ];
    }

    protected static function getDefaultPaginationPageOption(): int
    {
        return 50;
    }

    protected static function applyDefaults(Table $table): Table
    {
        return $table
            ->recordActions(static::getDefaultRecordActions())
            ->toolbarActions(static::getDefaultToolbarActions())
            ->defaultPaginationPageOption(static::getDefaultPaginationPageOption());
    }
}
