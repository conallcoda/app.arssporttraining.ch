<?php

namespace App\Training;

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
            $group = $getGroup($item);

            if (! $group) {
                continue;
            }

            $groupCounts[$group] = ($groupCounts[$group] ?? 0) + 1;
        }

        foreach ($items as $item) {
            $group = $getGroup($item);

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
}
