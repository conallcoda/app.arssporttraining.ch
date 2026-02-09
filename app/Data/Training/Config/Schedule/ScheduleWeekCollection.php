<?php

namespace App\Data\Training\Config\Schedule;

use Illuminate\Support\Collection;

class ScheduleWeekCollection
{
    /** @var Collection<int, ScheduleWeek> */
    protected Collection $items;

    /** @param Collection<int, ScheduleWeek> $items */
    public function __construct(Collection $items)
    {
        $this->items = $items;
    }

    /** @param array<int, array<string, mixed>> $weeks */
    public static function fromArray(array $weeks): self
    {
        return new self(
            collect($weeks)->map(fn(array $week, int $index) => ScheduleWeek::from([
                ...$week,
                'sort' => $week['sort'] ?? $index,
            ]))
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $defaultWeeks
     * @param  array<int, array<string, mixed>>  $userOverrides
     */
    public static function fromArrays(array $defaultWeeks, array $userOverrides = []): self
    {
        $defaults = self::fromArray($defaultWeeks);

        if (empty($userOverrides)) {
            return $defaults;
        }

        return $defaults->applyOverrides($userOverrides);
    }

    /** @param array<int, array<string, mixed>> $overrides */
    public function applyOverrides(array $overrides): self
    {
        $defaultWeekIds = $this->items->pluck('id')->all();

        $weeks = $this->items->map(function (ScheduleWeek $week) use ($overrides) {
            $override = $this->findOverride($overrides, $week->id);

            if (! $override) {
                return $week;
            }

            if (! empty($override['removed'])) {
                return null;
            }

            $data = $week->toArray();

            if (array_key_exists('linkedTo', $override)) {
                $data['linkedTo'] = $override['linkedTo'];
            }

            if (! empty($override['slots'])) {
                $data['slots'] = $this->mergeSlotOverrides($data['slots'] ?? [], $override['slots']);
            }

            return ScheduleWeek::from($data);
        })->filter();

        $userAddedWeeks = collect($overrides)
            ->filter(fn(array $override) => ! in_array($override['id'] ?? null, $defaultWeekIds))
            ->map(fn(array $week) => ScheduleWeek::from($week));

        $merged = $weeks->merge($userAddedWeeks)
            ->sortBy(fn(ScheduleWeek $week) => $week->sort)
            ->values();

        return new self($merged);
    }

    public function findById(string $id): ?ScheduleWeek
    {
        return $this->items->firstWhere('id', $id);
    }

    /** @param array<int, string> $defaultWeekIds */
    public function hasOverrides(array $defaultWeekIds): bool
    {
        foreach ($this->items as $week) {
            if (! in_array($week->id, $defaultWeekIds)) {
                return true;
            }

            if ($week->removed || ! empty($week->slots) || $week->linkedTo !== null) {
                return true;
            }
        }

        return false;
    }

    /** @return Collection<int, ScheduleWeek> */
    public function items(): Collection
    {
        return $this->items;
    }

    /** @param array<int, array<string, mixed>> $overrides */
    protected function findOverride(array $overrides, string $weekId): ?array
    {
        foreach ($overrides as $override) {
            if (($override['id'] ?? null) === $weekId) {
                return $override;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $baseSlots
     * @param  array<int, array<string, mixed>>  $overrideSlots
     * @return array<int, array<string, mixed>>
     */
    protected function mergeSlotOverrides(array $baseSlots, array $overrideSlots): array
    {
        $result = $baseSlots;

        foreach ($overrideSlots as $override) {
            $day = $override['day'];
            $slot = $override['slot'];
            $programId = $override['programId'] ?? null;

            $result = array_filter($result, fn($s) => ! ($s['day'] === $day && $s['slot'] === $slot));

            if ($programId !== null) {
                $result[] = $override;
            }
        }

        return array_values($result);
    }
}
