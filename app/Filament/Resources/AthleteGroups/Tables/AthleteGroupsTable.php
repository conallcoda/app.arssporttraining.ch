<?php

namespace App\Filament\Resources\AthleteGroups\Tables;

use App\Filament\Tables\AbstractTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AthleteGroupsTable extends AbstractTable
{
    public static function configure(Table $table): Table
    {
        return static::applyDefaults($table)
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Add filters as needed
            ])
            ->defaultSort('name');
    }
}
