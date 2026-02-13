<?php

namespace App\Data\Exercise\Preview;

class WeekOverride
{
    public function __construct(
        public int $week,
        /** @var array<string, mixed> */
        public array $data = [],
    ) {}

    /** @param array{week: int, data: array<string, mixed>} $array */
    public static function fromArray(array $array): self
    {
        return new self(
            week: $array['week'],
            data: $array['data'] ?? [],
        );
    }
}
