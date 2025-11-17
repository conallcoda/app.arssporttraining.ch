<?php

namespace App\Filament\Resources\Athletes\RelationManagers;

use App\Filament\Extensions\Actions\CreateAction;
use App\Models\Metrics\Metric;
use App\Models\Metrics\MetricType;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms;
use Filament\Schemas\Schema;

class MetricsRelationManager extends RelationManager
{
    protected static string $relationship = 'metrics';

    public function form(Schema $schema): Schema
    {
        return Metric::createForm($schema, 'athlete');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('metricType.name')
                    ->label('Type')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('value')
                    ->label('Value')
                    ->formatStateUsing(function ($state, $record) {
                        if (!$record->metricType) {
                            return $state;
                        }
                        $metricTypeModel = MetricType::getMetricTypeModel(
                            $record->metricType->scope,
                            $record->metricType->type
                        );

                        $unit = $metricTypeModel::unit(true);

                        return $unit ? "{$state} {$unit}" : $state;
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Recorded')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])

            ->defaultSort('created_at', 'desc');
    }
}
