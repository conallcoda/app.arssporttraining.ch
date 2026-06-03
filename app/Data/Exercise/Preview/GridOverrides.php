<?php

namespace App\Data\Exercise\Preview;

use App\Support\Training\GridOverrideNormalizer;
use Coda\Cms\Data\AbstractData;

class GridOverrides extends AbstractData
{
    public function __construct(
        /** @var SessionOverride[] */
        public array $sessions = [],
        /** @var CellOverride[] */
        public array $cells = [],
    ) {}

    /**
     * @param  array<int, array{week: int, session: int, data: array<string, mixed>}>  $sessions
     * @param  array<int, array{week: int, session: int, set: int, data: array<string, mixed>}>  $cells
     */
    public static function fromArrays(array $first = [], array $second = []): self
    {
        $sessions = $first;
        $cells = $second;

        if ($first !== [] && isset($first[0]['set'])) {
            $normalized = GridOverrideNormalizer::normalize([
                'cells' => $first,
                'weeks' => $second,
            ]);

            $sessions = $normalized['sessions'] ?? [];
            $cells = $normalized['cells'] ?? [];
        }

        return new self(
            sessions: array_map(fn (array $s) => SessionOverride::from($s), $sessions),
            cells: array_map(fn (array $c) => CellOverride::from($c), $cells),
        );
    }

    /** @param array{sessions?: array, cells?: array, weeks?: array} $overrides */
    public static function fromConfig(array $overrides): self
    {
        $normalized = GridOverrideNormalizer::normalize($overrides);

        return self::fromArrays(
            $normalized['sessions'] ?? [],
            $normalized['cells'] ?? [],
        );
    }

    public function hasCellOverride(int $week, int $set, string $field, ?int $session = null): bool
    {
        if ($session !== null) {
            $override = $this->findExactCellOverride($week, $session, $set);

            if ($override !== null) {
                return isset($override->data[$field]);
            }

            return false;
        }

        $override = $this->findExactCellOverride($week, 0, $set);

        return $override !== null && isset($override->data[$field]);
    }

    public function getCellOverrideValue(int $week, int $set, string $field, ?int $session = null): mixed
    {
        if ($session !== null) {
            $override = $this->findExactCellOverride($week, $session, $set);

            if ($override !== null) {
                return $override->data[$field] ?? null;
            }

            return null;
        }

        $override = $this->findExactCellOverride($week, 0, $set);

        return $override?->data[$field] ?? null;
    }

    public function hasSessionOverride(int $week, int $session, string $field): bool
    {
        foreach ($this->sessions as $override) {
            if ($override->week === $week && $override->session === $session && isset($override->data[$field])) {
                return true;
            }
        }

        return false;
    }

    public function getSessionOverrideValue(int $week, int $session, string $field): mixed
    {
        foreach ($this->sessions as $override) {
            if ($override->week === $week && $override->session === $session && isset($override->data[$field])) {
                return $override->data[$field];
            }
        }

        return null;
    }

    public function hasWeekOverride(int $week, string $field): bool
    {
        foreach ($this->sessions as $override) {
            if ($override->week === $week && isset($override->data[$field])) {
                return true;
            }
        }

        return false;
    }

    public function getWeekOverrideValue(int $week, string $field): mixed
    {
        foreach ($this->sessions as $override) {
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

    private function findExactCellOverride(int $week, int $session, int $set): ?CellOverride
    {
        foreach ($this->cells as $override) {
            if ($override->week === $week && $override->session === $session && $override->set === $set) {
                return $override;
            }
        }

        return null;
    }

    public function findSessionOverrideIndex(int $week, int $session): ?int
    {
        foreach ($this->sessions as $index => $override) {
            if ($override->week === $week && $override->session === $session) {
                return $index;
            }
        }

        return null;
    }

    public function isEmpty(): bool
    {
        return $this->cells === [] && $this->sessions === [];
    }
}
