<?php

namespace App\Data\Training\Config\Schedule;

use Coda\Cms\Data\AbstractConfig;

class ScheduleWeekSlot extends AbstractConfig
{
    /** @param int[] $programs */
    public function __construct(
        public int $day,
        public int $slot,
        public array $programs = [],
    ) {}

    public static function from(mixed ...$payloads): static
    {
        $payload = $payloads[0] ?? [];

        if (is_array($payload) && isset($payload['programId']) && ! isset($payload['programs'])) {
            $payload['programs'] = $payload['programId'] !== null ? [(int) $payload['programId']] : [];
            unset($payload['programId']);
        }

        return parent::from($payload);
    }
}
