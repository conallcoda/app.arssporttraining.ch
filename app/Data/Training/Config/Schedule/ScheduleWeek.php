<?php

namespace App\Data\Training\Config\Schedule;

use Coda\Cms\Data\AbstractConfig;

/**
 * @property ScheduleWeekSlot[] $slots
 */
class ScheduleWeek extends AbstractConfig
{
    public function __construct(
        public string $id,
        public ?string $linkedTo = null,
        public int $sort = 0,
        public array $slots = [],
    ) {}

    /** @return array<int, array<int, array{programs: int[]}>> */
    public function grid(): array
    {
        $grid = [];
        for ($day = 0; $day < 7; $day++) {
            $grid[$day] = [
                0 => ['programs' => []],
                1 => ['programs' => []],
            ];
        }

        foreach ($this->slots as $slot) {
            $grid[$slot->day][$slot->slot] = [
                'programs' => $slot->programs,
            ];
        }

        return $grid;
    }
}
