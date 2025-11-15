<?php

namespace App\Filament\Resources\Athletes\Tables;

use App\Filament\Extensions\AbstractTable;
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

                TextColumn::make('members')
                    ->label('Members')
                    ->getStateUsing(fn($record) => $record->members->pluck('name')->toArray())
                    ->badge()
                    ->separator(',')
                    ->wrap(),
            ])
            ->filters([
                // Add filters as needed
            ])
            ->defaultSort('name');
    }
}
