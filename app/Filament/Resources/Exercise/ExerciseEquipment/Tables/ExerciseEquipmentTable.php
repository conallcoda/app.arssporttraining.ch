<?php

namespace App\Filament\Resources\Exercise\ExerciseEquipment\Tables;

use App\Filament\Tables\AbstractTable;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ExerciseEquipmentTable extends AbstractTable
{
    public static function configure(Table $table): Table
    {
        return static::applyDefaults($table)
            ->columns([
                TextInputColumn::make('name')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->defaultSort('name')
            ->recordActions([
                DeleteAction::make(),
            ]);
    }
}
