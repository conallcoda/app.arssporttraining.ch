<?php

namespace App\Filament\Resources\Training\Tables;

use App\Filament\Extensions\AbstractTable;
use App\Filament\Forms\Components\ColorPicker;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class TrainingSessionCategoriesTable extends AbstractTable
{
    public static function configure(Table $table): Table
    {
        return static::applyDefaults($table)
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                ColorColumn::make('text_color')
                    ->label('Text Color')
                    ->state(fn ($record) => ColorPicker::getColorConstant($record->text_color)),

                ColorColumn::make('background_color')
                    ->label('Background Color')
                    ->state(fn ($record) => ColorPicker::getColorConstant($record->background_color)),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->defaultSort('name');
    }
}
