<?php

namespace App\Filament\Resources\AthleteMetricTypes;

use App\Filament\Resources\AthleteMetricTypes\Pages\ManageAthleteMetricTypes;
use App\Models\Metrics\MetricType;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class AthleteMetricTypesResource extends Resource
{
    protected static ?string $model = MetricType::class;

    protected static UnitEnum|string|null $navigationGroup = 'Athletes';

    protected static string|BackedEnum|null $navigationIcon = 'lucide-ruler';

    protected static ?string $navigationLabel = 'Metrics';

    protected static ?string $modelLabel = 'Metric';

    protected static ?string $pluralModelLabel = 'Metrics';

    protected static ?string $breadcrumb = 'Athlete Metrics';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('model_base')
                    ->default('user'),

                Hidden::make('model_sub')
                    ->default('athlete'),

                Select::make('type')
                    ->required()
                    ->options(function () {
                        $allowedTypes = MetricType::getAllowedMetricTypesFor('user', 'athlete');

                        $labels = [
                            'boolean' => 'Boolean',
                            'duration' => 'Duration',
                            'height' => 'Height',
                            'number' => 'Number',
                            'one_rep_max' => 'One Rep Max',
                            'percentage' => 'Percentage',
                            'time_under_tension' => 'Time Under Tension',
                            'weight' => 'Weight',
                        ];

                        $options = [];
                        foreach ($allowedTypes as $type) {
                            $options[$type] = $labels[$type] ?? ucwords(str_replace('_', ' ', $type));
                        }

                        return $options;
                    })
                    ->native(false),

                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
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
            ->defaultSort('name')
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAthleteMetricTypes::route('/'),
        ];
    }
}
