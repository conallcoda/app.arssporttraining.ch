<?php

namespace App\Filament\Resources\Exercises\Tables;

use App\Filament\Extensions\AbstractTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ExerciseMusclesTable extends AbstractTable
{
    public static function configure(Table $table): Table
    {
        return static::applyDefaults($table)
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->defaultSort('name');
    }
}
