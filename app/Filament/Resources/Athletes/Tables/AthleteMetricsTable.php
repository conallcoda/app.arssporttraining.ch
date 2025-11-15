<?php

namespace App\Filament\Resources\Athletes\Tables;

use App\Filament\Extensions\AbstractTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class AthleteMetricsTable extends AbstractTable
{
    public static function configure(Table $table): Table
    {
        return static::applyDefaults($table)
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->formatStateUsing(fn(string $state): string => str_replace('_', ' ', ucwords($state, '_')))
                    ->badge()
                    ->color('success')
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->defaultSort('name');
    }
}
