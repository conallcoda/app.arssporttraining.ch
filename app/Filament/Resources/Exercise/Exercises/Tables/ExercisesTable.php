<?php

namespace App\Filament\Resources\Exercise\Exercises\Tables;

use App\Filament\Tables\AbstractTable;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\Level;
use App\Models\Exercise\Mechanic;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Awcodes\BadgeableColumn\Components\BadgeableColumn;

class ExercisesTable extends AbstractTable
{
    public static function configure(Table $table): Table
    {
        return static::applyDefaults($table)
            ->columns([

                BadgeableColumn::make('name')
                    ->suffixBadges(Exercise::getBadges())
                    ->separator(false)
                    ->searchable()
                    ->sortable(),

                /*
                TextColumn::make('equipment.name')
                    ->label('Equipment')
                    ->badge()
                    ->separator(',')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
             
                TextColumn::make('level')
                    ->badge()
                    ->sortable(),
                TextColumn::make('mechanic')
                    ->badge()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('primaryMuscles.name')
                    ->label('Primary Muscles')
                    ->badge()
                    ->separator(',')
                    ->toggleable(),
                    */
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'strength' => 'strength',
                        'plyometric' => 'plyometric',
                        'stretching' => 'stretching',
                        'cardio' => 'cardio',
                    ])
                    ->native(false),
                SelectFilter::make('level')
                    ->options(Level::class)
                    ->native(false),
                SelectFilter::make('mechanic')
                    ->options(Mechanic::class)
                    ->native(false),
                SelectFilter::make('equipment')
                    ->label('Equipment')
                    ->relationship('equipment', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple()
                    ->native(false),
                TrashedFilter::make(),
            ])
            ->defaultSort('name');
    }
}
