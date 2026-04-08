<?php

namespace App\Data\Athlete;

use Carbon\Carbon;
use Coda\Cms\Data\AbstractData;

class ReadinessData extends AbstractData
{
    public function __construct(
        public int $userId,
        public string $date,
        public ?bool $isReady = null,
    ) {}

    public static function forToday(int $userId): self
    {
        return new self(
            userId: $userId,
            date: Carbon::today()->format('Y-m-d'),
        );
    }
}
