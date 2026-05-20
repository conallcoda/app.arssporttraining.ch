<?php

namespace App\Support\Training;

use Illuminate\Support\Collection;

class ProgramExerciseOrder
{
    private const SECTION_ORDER = [
        'warm_up' => 0,
        'main' => 1,
        'warm_down' => 2,
    ];

    /**
     * @param  iterable<mixed>  $exercises
     * @return Collection<int, mixed>
     */
    public function sortProgramExercises(iterable $exercises, bool $includeType = true): Collection
    {
        return $this->sortItems(
            $exercises,
            typeResolver: fn (mixed $exercise): ?string => $includeType ? (string) data_get($exercise, 'pivot.type', 'main') : null,
            groupResolver: fn (mixed $exercise): ?string => data_get($exercise, 'pivot.group'),
            sortResolver: fn (mixed $exercise): int => (int) data_get($exercise, 'pivot.sort', 0),
            stableResolver: fn (mixed $exercise, int $index): string => (string) data_get($exercise, 'pivot.id', data_get($exercise, 'id', $index)),
        );
    }

    /**
     * @param  iterable<mixed>  $exercises
     * @return Collection<int, mixed>
     */
    public function sortSlotExercises(iterable $exercises, bool $includeType = true): Collection
    {
        return $this->sortItems(
            $exercises,
            typeResolver: fn (mixed $exercise): ?string => $includeType ? (string) data_get($exercise, 'type', 'main') : null,
            groupResolver: fn (mixed $exercise): ?string => data_get($exercise, 'group'),
            sortResolver: fn (mixed $exercise): int => (int) data_get($exercise, 'sort', 0),
            stableResolver: fn (mixed $exercise, int $index): string => (string) data_get($exercise, 'slotExerciseId', data_get($exercise, 'id', $index)),
        );
    }

    /**
     * @param  iterable<array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    public function sortRows(iterable $rows, bool $includeType = false): Collection
    {
        return $this->sortItems(
            $rows,
            typeResolver: fn (array $row): ?string => $includeType ? (string) ($row['type'] ?? 'main') : null,
            groupResolver: fn (array $row): ?string => $row['group'] ?? null,
            sortResolver: fn (array $row): int => (int) ($row['sort'] ?? 0),
            stableResolver: fn (array $row, int $index): string => (string) ($row['program_exercise_id'] ?? $row['source_program_exercise_id'] ?? $row['_key'] ?? $row['id'] ?? $index),
        )->map(fn (mixed $row): array => is_array($row) ? $row : []);
    }

    /**
     * @param  iterable<array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function normalizeRows(iterable $rows, bool $includeType = false): array
    {
        $counters = [];

        $prepared = collect($rows)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->values()
            ->map(function (array $row) use (&$counters, $includeType): array {
                $group = $this->normalizeGroup($row['group'] ?? null);
                $type = $includeType ? (string) ($row['type'] ?? 'main') : null;
                $counterKey = $this->counterKey($type, $group);

                $row['group'] = $group;
                $row['_key'] = $row['_key'] ?? uniqid('item_', true);
                $row['sort'] = $counters[$counterKey] ?? 0;

                $counters[$counterKey] = $row['sort'] + 1;

                return $row;
            });

        return $this->sortRows($prepared, $includeType)->values()->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function canMoveRow(array $rows, int $index, int $direction, bool $includeType = false): bool
    {
        $targetIndex = $index + $direction;

        if (! isset($rows[$index], $rows[$targetIndex])) {
            return false;
        }

        return $this->movementKey($rows[$index], $includeType) === $this->movementKey($rows[$targetIndex], $includeType);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function moveRow(array $rows, int $index, int $direction, bool $includeType = false): array
    {
        if (! $this->canMoveRow($rows, $index, $direction, $includeType)) {
            return $this->normalizeRows($rows, $includeType);
        }

        $targetIndex = $index + $direction;
        [$rows[$index], $rows[$targetIndex]] = [$rows[$targetIndex], $rows[$index]];

        return $this->normalizeRows($rows, $includeType);
    }

    /**
     * @param  iterable<mixed>  $items
     * @return Collection<int, mixed>
     */
    private function sortItems(
        iterable $items,
        callable $typeResolver,
        callable $groupResolver,
        callable $sortResolver,
        callable $stableResolver,
    ): Collection {
        $wrapped = collect($items)
            ->values()
            ->map(fn (mixed $item, int $index): array => [
                'item' => $item,
                'type' => $this->normalizeType($typeResolver($item)),
                'group' => $this->normalizeGroup($groupResolver($item)),
                'sort' => (int) $sortResolver($item),
                'stable' => (string) $stableResolver($item, $index),
                'index' => $index,
            ])
            ->all();

        usort($wrapped, function (array $left, array $right): int {
            $typeComparison = $this->compareType($left['type'], $right['type']);

            if ($typeComparison !== 0) {
                return $typeComparison;
            }

            $groupComparison = $this->compareGroup($left['group'], $right['group']);

            if ($groupComparison !== 0) {
                return $groupComparison;
            }

            $sortComparison = $left['sort'] <=> $right['sort'];

            if ($sortComparison !== 0) {
                return $sortComparison;
            }

            $stableComparison = strnatcasecmp($left['stable'], $right['stable']);

            if ($stableComparison !== 0) {
                return $stableComparison;
            }

            return $left['index'] <=> $right['index'];
        });

        return collect($wrapped)
            ->map(fn (array $wrappedItem) => $wrappedItem['item'])
            ->values();
    }

    private function movementKey(array $row, bool $includeType): string
    {
        $type = $includeType ? $this->normalizeType((string) ($row['type'] ?? 'main')) : null;
        $group = $this->normalizeGroup($row['group'] ?? null);

        return $this->counterKey($type, $group);
    }

    private function counterKey(?string $type, ?string $group): string
    {
        return ($type ?? 'main').'|'.($group ?? '__ungrouped__');
    }

    private function normalizeType(?string $type): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }

        return $type;
    }

    private function normalizeGroup(?string $group): ?string
    {
        if ($group === null) {
            return null;
        }

        $group = trim($group);

        return $group === '' ? null : $group;
    }

    private function compareType(?string $left, ?string $right): int
    {
        if ($left === $right) {
            return 0;
        }

        return $this->typeRank($left) <=> $this->typeRank($right);
    }

    private function typeRank(?string $type): int
    {
        if ($type === null) {
            return 0;
        }

        return self::SECTION_ORDER[$type] ?? count(self::SECTION_ORDER);
    }

    private function compareGroup(?string $left, ?string $right): int
    {
        if ($left === $right) {
            return 0;
        }

        if ($left === null) {
            return -1;
        }

        if ($right === null) {
            return 1;
        }

        return strnatcasecmp($left, $right);
    }
}
