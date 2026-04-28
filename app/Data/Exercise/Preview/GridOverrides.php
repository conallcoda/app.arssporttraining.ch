<?php

namespace App\Data\Exercise\Preview;

use Coda\Cms\Data\AbstractData;

class GridOverrides extends AbstractData
{
    public function __construct(
        /** @var CellOverride[] */
        public array $cells = [],
        /** @var WeekOverride[] */
        public array $weeks = [],
    ) {}

    /**
     * @param  array<int, array{week: int, session: int, set: int, data: array<string, mixed>}>  $cells
     * @param  array<int, array{week: int, data: array<string, mixed>}>  $weeks
     */
    public static function fromArrays(array $cells = [], array $weeks = []): self
    {
        return new self(
            cells: array_map(fn (array $c) => CellOverride::from($c), $cells),
            weeks: array_map(fn (array $w) => WeekOverride::from($w), $weeks),
        );
    }

    public function hasCellOverride(int $week, int $set, string $field, ?int $session = null): bool
    {
        if ($session !== null) {
            foreach ($this->cells as $override) {
                if (
                    $override->week === $week
                    && $override->set === $set
                    && $override->session === $session
                    && isset($override->data[$field])
                ) {
                    return true;
                }
            }

            return false;
        }

        foreach ($this->cells as $override) {
            if (
                $override->week === $week
                && $override->set === $set
                && $session === null
                && isset($override->data[$field])
            ) {
                return true;
            }
        }

        return false;
    }

    public function getCellOverrideValue(int $week, int $set, string $field, ?int $session = null): mixed
    {
        if ($session !== null) {
            foreach ($this->cells as $override) {
                if (
                    $override->week === $week
                    && $override->set === $set
                    && $override->session === $session
                    && isset($override->data[$field])
                ) {
                    return $override->data[$field];
                }
            }

            return null;
        }

        foreach ($this->cells as $override) {
            if ($override->week === $week && $override->set === $set && isset($override->data[$field])) {
                return $override->data[$field];
            }
        }

        return null;
    }

    public function hasWeekOverride(int $week, string $field): bool
    {
        foreach ($this->weeks as $override) {
            if ($override->week === $week && isset($override->data[$field])) {
                return true;
            }
        }

        return false;
    }

    public function getWeekOverrideValue(int $week, string $field): mixed
    {
        foreach ($this->weeks as $override) {
            if ($override->week === $week && isset($override->data[$field])) {
                return $override->data[$field];
            }
        }

        return null;
    }

    public function findCellOverrideIndex(int $week, int $session, int $set): ?int
    {
        foreach ($this->cells as $index => $override) {
            if ($override->week === $week && $override->session === $session && $override->set === $set) {
                return $index;
            }
        }

        return null;
    }

    public function findWeekOverrideIndex(int $week): ?int
    {
        foreach ($this->weeks as $index => $override) {
            if ($override->week === $week) {
                return $index;
            }
        }

        return null;
    }

    public function isEmpty(): bool
    {
        return $this->cells === [] && $this->weeks === [];
    }
}
