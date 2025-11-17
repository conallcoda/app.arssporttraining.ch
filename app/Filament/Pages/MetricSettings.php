<?php

namespace App\Filament\Pages;

use BackedEnum;
use Inerba\DbConfig\AbstractPageSettings;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Icetalker\FilamentTableRepeater\Forms\Components\TableRepeater;
use Filament\Forms\Components\TextInput;

class MetricSettings extends AbstractPageSettings
{
    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    protected static ?string $title = 'Metrics';

    protected static ?int $navigationSort = 100;

    // protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-wrench-screwdriver'; // Uncomment if you want to set a custom navigation icon

    // protected ?string $subheading = ''; // Uncomment if you want to set a custom subheading

    // protected static ?string $slug = 'metrics-settings'; // Uncomment if you want to set a custom slug

    protected string $view = 'filament.pages.metrics-settings';

    protected function settingName(): string
    {
        return 'metrics';
    }

    /**
     * Provide default values.
     *
     * @return array<string, mixed>
     */
    public function getDefaultData(): array
    {
        $conversionTable = [
            1 => 1,
            2 => 0.96,
            3 => 0.94,
            4 => 0.91,
            5 => 0.88,
            6 => 0.86,
            7 => 0.83,
            8 => 0.8,
            9 => 0.77,
            10 => 0.74,
            11 => 0.71,
            12 => 0.67,
            13 => 0.65,
            14 => 0.63,
            15 => 0.62,
        ];

        $data['1rm_conversions'] = collect($conversionTable)->map(function ($value, $reps) {
            return [
                'value' => $value,
            ];
        })->values()->toArray();

        return $data;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TableRepeater::make('1rm_conversions')
                    ->label('Conversions')
                    ->schema([
                        TextInput::make('reps')
                            ->label('Reps')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('value')->numeric()->step(0.01)->label('Weight Multiplier'),
                    ])
                    ->reorderable(false)
                    ->cloneable(false)
                    ->collapsible()
                    ->minItems(15)
                    ->columnSpan('full')
                    ->afterStateHydrated(function (TableRepeater $component, $state) {
                        if (!is_array($state)) {
                            return;
                        }

                        $updatedState = [];
                        foreach ($state as $index => $item) {
                            $updatedState[$index] = $item;
                            $updatedState[$index]['reps'] = $index + 1;
                        }

                        $component->state($updatedState);
                    })
                    ->afterStateUpdated(function (TableRepeater $component, $state) {
                        if (!is_array($state)) {
                            return;
                        }

                        $updatedState = [];
                        $numericIndex = 0;
                        foreach ($state as $index => $item) {
                            $updatedState[$index] = is_array($item) ? $item : [];
                            $updatedState[$index]['reps'] = (string)($numericIndex + 1);
                            $numericIndex++;
                        }

                        $component->state($updatedState);
                    }),
            ])
            ->statePath('data');
    }
}
