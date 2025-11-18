<?php

namespace App\Filament\Pages;

use BackedEnum;
use Inerba\DbConfig\AbstractPageSettings;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Icetalker\FilamentTableRepeater\Forms\Components\TableRepeater;
use Filament\Forms\Components\TextInput;
use UnitEnum;

class OneRepMaxSettings extends AbstractPageSettings
{
    public ?array $data = [];

    protected static ?string $title = 'One Rep Max';

    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.pages.metrics-settings';

    public static function getNavigationGroup(): ?string
    {
        return 'Training';
    }

    protected function settingName(): string
    {
        return 'metrics';
    }

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
