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
        return "bg-{$color}-100 dark:bg-{$color}-900/20";
    }

    public static function blockTint(string $color): string
    {
        return "bg-{$color}-500/10 dark:bg-{$color}-500/10";
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

    public static function safelist(): string
    {
        $classes = [];

        foreach (array_keys(self::COLORS) as $color) {
            $classes[] = self::solidClasses($color);
            $classes[] = self::light($color);
            $classes[] = self::blockTint($color);
            $classes[] = self::lightBadge($color);
            $classes[] = self::lightOpaque($color);
            $classes[] = self::lightOpaqueSubtle($color);
            $classes[] = self::lightStrong($color);
        }

        $all = [];
        foreach ($classes as $classString) {
            foreach (preg_split('/\s+/', $classString) as $class) {
                $all[$class] = true;
            }
        }

        return implode(' ', array_keys($all));
    }
}
