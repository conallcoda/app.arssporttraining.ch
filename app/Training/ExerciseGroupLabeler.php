<?php

namespace App\Training;

use Coda\Cms\Support\ColorPalette;

class ExerciseGroupLabeler
{
    /**
     * Generate group labels, using A/B/C for single-item groups and A1/A2/B1 for repeated groups.
     *
     * @param  iterable<array-key, object>  $items
     * @param  callable(object): ?string  $getGroup
     * @param  callable(object): int|string  $getId
     * @return array<int|string, string>
     */
    public static function label(iterable $items, callable $getGroup, callable $getId): array
    {
        $items = is_array($items) ? $items : iterator_to_array($items, false);
        $labels = [];
        $groupCounts = [];
        $counters = [];

        foreach ($items as $item) {
            $group = self::normalizeGroup($getGroup($item));

            if (! $group) {
                continue;
            }

            $groupCounts[$group] = ($groupCounts[$group] ?? 0) + 1;
        }

        foreach ($items as $item) {
            $group = self::normalizeGroup($getGroup($item));

            if (! $group) {
                continue;
            }

            $counters[$group] = ($counters[$group] ?? 0) + 1;
            $labels[$getId($item)] = ($groupCounts[$group] ?? 0) > 1
                ? $group.$counters[$group]
                : $group;
        }

        return $labels;
    }

    /**
     * Assign stable palette colors to grouped items. Ungrouped items are omitted.
     *
     * @param  iterable<array-key, object>  $items
     * @param  callable(object): ?string  $getGroup
     * @param  callable(object): int|string  $getId
     * @return array<int|string, string>
     */
    public static function colors(iterable $items, callable $getGroup, callable $getId): array
    {
        $items = is_array($items) ? $items : iterator_to_array($items, false);
        $palette = array_values(ColorPalette::ROW_COLORS);
        $groupColors = [];
        $itemColors = [];

        if ($palette === []) {
            return [];
        }

        $groups = collect($items)
            ->map(fn (object $item): ?string => self::normalizeGroup($getGroup($item)))
            ->filter()
            ->unique(fn (string $group): string => mb_strtolower($group))
            ->sort(fn (string $left, string $right): int => strnatcasecmp($left, $right))
            ->values();

        foreach ($groups as $index => $group) {
            $groupColors[$group] = $palette[$index % count($palette)];
        }

        foreach ($items as $item) {
            $group = self::normalizeGroup($getGroup($item));

            if (! $group || ! isset($groupColors[$group])) {
                continue;
            }

            $itemColors[$getId($item)] = $groupColors[$group];
        }

        return $itemColors;
    }

    private static function normalizeGroup(?string $group): ?string
    {
        if ($group === null) {
            return null;
        }

        $group = trim($group);

        return $group === '' ? null : $group;
    }
}
