<?php

namespace App\Data\Training\Config\Schedule;

use App\Cms\Data\AbstractConfig;
use Spatie\LaravelData\Optional;

/**
 * @property ScheduleWeekSlot[] $slots
 */
class ScheduleWeek extends AbstractConfig
{
    public function __construct(
        public string $id,
        public string|null|Optional $linkedTo,
        public int|Optional $sort,
        public array $slots,
        public bool|Optional $removed,
    ) {}

    /** @return array<int, array<int, array{programId: int|null}>> */
    public function grid(): array
    {
        $grid = [];
        for ($day = 0; $day < 7; $day++) {
            $grid[$day] = [
                0 => ['programId' => null],
                1 => ['programId' => null],
            ];
        }

        foreach ($this->slots as $slot) {
            $grid[$slot->day][$slot->slot] = [
                'programId' => $slot->programId,
            ];
        }

        return $grid;
    }
}
