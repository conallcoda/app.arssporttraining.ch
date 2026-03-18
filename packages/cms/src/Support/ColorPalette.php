<?php

namespace Coda\Cms\Support;

class ColorPalette
{
    public const COLORS = [
        'slate' => 'Slate',
        'gray' => 'Gray',
        //   'zinc' => 'Zinc',
        //   'neutral' => 'Neutral',
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

    public const ROW_COLORS = [
        'blue',
        'green',
        'red',
        'purple',
        'cyan',
        'orange',
        'pink',
        'amber',
    ];

    public static function solid(string $color): string
    {
        return "background-color: var(--color-{$color}-500); color: white;";
    }

    public static function solidClasses(string $color): string
    {
        return "dark:bg-{$color}-600 bg-{$color}-600 !text-white";
    }

    public static function light(string $color): string
    {
        return "bg-{$color}-100 dark:bg-{$color}-400";
    }

    public static function lightBadge(string $color): string
    {
        return "!bg-{$color}-100 dark:!bg-{$color}-900/30 !text-{$color}-700 dark:!text-{$color}-300";
    }

    public static function lightOpaque(string $color): string
    {
        return "bg-{$color}-300 dark:bg-{$color}-950";
    }

    public static function lightOpaqueSubtle(string $color): string
    {
        return "bg-{$color}-200 dark:bg-{$color}-900";
    }

    public static function lightStrong(string $color): string
    {
        return "bg-{$color}-200 dark:bg-{$color}-700/40";
    }
}
