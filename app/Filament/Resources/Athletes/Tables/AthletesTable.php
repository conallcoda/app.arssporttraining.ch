<?php

namespace App\Filament\Resources\Athletes\Tables;

use App\Filament\Extensions\AbstractTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AthletesTable extends AbstractTable
{
    public static function configure(Table $table): Table
    {
        return static::applyDefaults($table)
            ->columns([
                TextColumn::make('forename')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('surname')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('phone')
                    ->searchable()
                    ->sortable()
                    ->toggleable()
                    ->copyable(),

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
            ->defaultSort('surname');
    }
}
