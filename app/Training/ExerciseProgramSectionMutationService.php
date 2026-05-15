<?php

namespace App\Training;

use App\Data\Training\Config\ExercisePlanConfig;
use App\Models\Exercise\Exercise;
use Illuminate\Support\Collection;

class ExerciseProgramSectionMutationService
{
    /**
     * @param  Collection<int, Exercise>  $currentRows
     * @param  Collection<int, array<string, mixed>>  $proposedRows
     * @param  array<int, array<int, bool>>  $lockedSessionsByWeek
     * @param  array<int, array<int, string|null>>  $weekSessionDates
     * @return array{
     *   rows: array<int, array<string, mixed>>,
     *   startsAtDate: ?string,
     *   preservedImmutableCount: int
     * }
     */
    public function normalize(
        Collection $currentRows,
        Collection $proposedRows,
        ExercisePlanConfig $config,
        array $lockedSessionsByWeek = [],
        array $weekSessionDates = [],
    ): array {
        $immutableDates = $this->immutableSessionDates($lockedSessionsByWeek, $weekSessionDates);
        $startsAtDate = $immutableDates !== [] ? $this->firstEditableSessionDate($lockedSessionsByWeek, $weekSessionDates) : null;

        $rows = $proposedRows
            ->filter(fn (mixed $row) => is_array($row) && ! empty($row['id']))
            ->values()
            ->map(fn (array $row) => $row)
            ->all();

        $preservedImmutableCount = 0;

        if ($immutableDates !== []) {
            $historicalRows = $currentRows
                ->filter(fn (Exercise $exercise) => $this->existsInImmutableHistory($exercise, $config, $immutableDates))
                ->keyBy(fn (Exercise $exercise) => (int) $exercise->pivot->id);

            foreach ($historicalRows as $pivotId => $exercise) {
                $index = collect($rows)->search(fn (array $row) => (int) ($row['program_exercise_id'] ?? 0) === (int) $pivotId);

                if ($index === false) {
                    $rows[] = [
                        'id' => $exercise->id,
                        'program_exercise_id' => (int) $exercise->pivot->id,
                        '_key' => uniqid('item_', true),
                        'group' => $exercise->pivot->group,
                    ];
                    $preservedImmutableCount++;

                    continue;
                }

                if ((int) ($rows[$index]['id'] ?? 0) !== (int) $exercise->id) {
                    $rows[$index]['id'] = $exercise->id;
                    unset($rows[$index]['source_program_id'], $rows[$index]['source_program_exercise_id']);
                    $preservedImmutableCount++;
                }
            }
        }

        $rows = array_values(array_map(function (array $row, int $index): array {
            $row['_key'] = $row['_key'] ?? uniqid('item_', true);
            $row['sort'] = $index;

            return $row;
        }, $rows, array_keys($rows)));

        return [
            'rows' => $rows,
            'startsAtDate' => $startsAtDate,
            'preservedImmutableCount' => $preservedImmutableCount,
        ];
    }

    /**
     * @param  array<int, array<int, bool>>  $lockedSessionsByWeek
     * @param  array<int, array<int, string|null>>  $weekSessionDates
     * @return array<int, string>
     */
    private function immutableSessionDates(array $lockedSessionsByWeek, array $weekSessionDates): array
    {
        $dates = [];

        foreach ($lockedSessionsByWeek as $weekIndex => $sessions) {
            foreach ($sessions as $sessionIndex => $locked) {
                if (! $locked) {
                    continue;
                }

                $date = $weekSessionDates[$weekIndex][$sessionIndex] ?? null;

                if (is_string($date) && $date !== '') {
                    $dates[] = $date;
                }
            }
        }

        $dates = array_values(array_unique($dates));
        sort($dates);

        return $dates;
    }

    /**
     * @param  array<int, array<int, bool>>  $lockedSessionsByWeek
     * @param  array<int, array<int, string|null>>  $weekSessionDates
     */
    private function firstEditableSessionDate(array $lockedSessionsByWeek, array $weekSessionDates): ?string
    {
        $dates = [];

        foreach ($weekSessionDates as $weekIndex => $sessions) {
            foreach ($sessions as $sessionIndex => $date) {
                if (($lockedSessionsByWeek[$weekIndex][$sessionIndex] ?? false) === true) {
                    continue;
                }

                if (is_string($date) && $date !== '') {
                    $dates[] = $date;
                }
            }
        }

        if ($dates === []) {
            return null;
        }

        sort($dates);

        return $dates[0];
    }

    /**
     * @param  array<int, string>  $immutableDates
     */
    private function existsInImmutableHistory(Exercise $exercise, ExercisePlanConfig $config, array $immutableDates): bool
    {
        $pivotId = (int) ($exercise->pivot->id ?? 0);

        if ($pivotId <= 0) {
            return false;
        }

        $startsAtDate = $config->defaultExerciseOverrides($pivotId)->startsAtDate;

        if ($startsAtDate === null || $startsAtDate === '') {
            return true;
        }

        foreach ($immutableDates as $immutableDate) {
            if ($immutableDate >= $startsAtDate) {
                return true;
            }
        }

        return false;
    }
}
