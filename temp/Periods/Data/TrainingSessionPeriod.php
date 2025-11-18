<?php

namespace App\Models\Training\Periods\Data;

use App\Data\AbstractData;

class TrainingSessionPeriod extends AbstractData
{
    public function __construct(
        public int $day,
        public int $sequence,
    ) {}

    public function day_label(): string
    {
        return match ($this->day) {
            0 => 'Monday',
            1 => 'Tuesday',
            2 => 'Wednesday',
            3 => 'Thursday',
            4 => 'Friday',
            5 => 'Saturday',
            6 => 'Sunday',
        };
    }

    public function sequence_label(): string
    {
        return match ($this->sequence) {
            0 => 'Morning',
            1 => 'Afternoon',
        };
    }

    public function label(): string
    {
        return $this->day_label() . ' - ' . $this->sequence_label();
    }
}
