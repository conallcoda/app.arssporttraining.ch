<?php

namespace App\Filament\Resources\Athletes\Tables;

use App\Filament\Extensions\AbstractTable;
use App\Models\Metrics\MetricType;
use Awcodes\BadgeableColumn\Components\Badge;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Awcodes\BadgeableColumn\Components\BadgeableColumn;
use Filament\Support\Colors\Color;

class AthleteCustomMetricsTable extends AbstractTable
{
    public static function configure(Table $table): Table
    {
        return static::applyDefaults($table)
            ->columns([
                BadgeableColumn::make('label')
                    ->suffixBadges([Badge::make('type')
                        ->label(fn(MetricType $record) => $record->name)
                        ->color(Color::Red)])
                    ->separator(false)
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
