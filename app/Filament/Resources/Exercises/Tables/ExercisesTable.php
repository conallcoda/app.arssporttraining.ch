<?php

namespace App\Filament\Resources\Exercises\Tables;

use App\Filament\Extensions\AbstractTable;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\Level;
use App\Models\Exercise\Mechanic;
use Awcodes\BadgeableColumn\Components\BadgeableColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

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
