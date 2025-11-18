<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Select;
use Filament\Support\Colors\Color;
use Illuminate\Support\HtmlString;

class ColorPicker extends Select
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->options(static::getColorOptions())
            ->searchable()
            ->live()
            ->suffix(
                fn ($state) => $state
                    ? new HtmlString('<div style="width: 24px; height: 24px; border-radius: 6px; background-color: ' . static::getColorValue($state) . '; border: 1px solid rgba(0,0,0,0.1);"></div>')
                    : null
            );
    }

    protected static function getColorOptions(): array
    {
        return [
            'black' => 'Black',
            'white' => 'White',
            'slate' => 'Slate',
            'gray' => 'Gray',
            'zinc' => 'Zinc',
            'neutral' => 'Neutral',
            'stone' => 'Stone',
            'red' => 'Red',
            'orange' => 'Orange',
            'amber' => 'Amber',
            'yellow' => 'Yellow',
            'lime' => 'Lime',
            'green' => 'Green',
            'emerald' => 'Emerald',
            'teal' => 'Teal',
            'cyan' => 'Cyan',
            'sky' => 'Sky',
            'blue' => 'Blue',
            'indigo' => 'Indigo',
            'violet' => 'Violet',
            'purple' => 'Purple',
            'fuchsia' => 'Fuchsia',
            'pink' => 'Pink',
            'rose' => 'Rose',
        ];
    }

    public static function getColorValue(string $colorName): string
    {
        if ($colorName === 'black') {
            return '#000000';
        }

        if ($colorName === 'white') {
            return '#FFFFFF';
        }

        $colorMap = [
            'slate' => Color::Slate,
            'gray' => Color::Gray,
            'zinc' => Color::Zinc,
            'neutral' => Color::Neutral,
            'stone' => Color::Stone,
            'red' => Color::Red,
            'orange' => Color::Orange,
            'amber' => Color::Amber,
            'yellow' => Color::Yellow,
            'lime' => Color::Lime,
            'green' => Color::Green,
            'emerald' => Color::Emerald,
            'teal' => Color::Teal,
            'cyan' => Color::Cyan,
            'sky' => Color::Sky,
            'blue' => Color::Blue,
            'indigo' => Color::Indigo,
            'violet' => Color::Violet,
            'purple' => Color::Purple,
            'fuchsia' => Color::Fuchsia,
            'pink' => Color::Pink,
            'rose' => Color::Rose,
        ];

        $palette = $colorMap[$colorName] ?? Color::Gray;
        return $palette[500] ?? $palette[array_key_first($palette)];
    }

    public static function getColorConstant(string $colorName): string
    {
        return static::getColorValue($colorName);
    }
}
