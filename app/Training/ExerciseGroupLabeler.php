<?php

namespace App\Training;

class ExerciseGroupLabeler
{
    /**
     * Generate A1/A2/B1-style labels for items that share a group.
     *
     * @param  iterable<array-key, object>  $items
     * @param  callable(object): ?string  $getGroup
     * @param  callable(object): int|string  $getId
     * @return array<int|string, string>
     */
    public static function label(iterable $items, callable $getGroup, callable $getId): array
    {
        $labels = [];
        $counters = [];

        foreach ($items as $item) {
            $group = $getGroup($item);

            if (! $group) {
                continue;
            }

            $counters[$group] = ($counters[$group] ?? 0) + 1;
            $labels[$getId($item)] = $group.$counters[$group];
        }

        return $labels;
    }
}
