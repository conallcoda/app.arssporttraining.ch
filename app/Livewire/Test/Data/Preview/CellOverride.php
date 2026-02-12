<?php

namespace App\Livewire\Test\Data\Preview;

class CellOverride
{
    public function __construct(
        public int $week,
        public int $session,
        public int $set,
        /** @var array<string, mixed> */
        public array $data = [],
    ) {}

    /** @param array{week: int, session: int, set: int, data: array<string, mixed>} $array */
    public static function fromArray(array $array): self
    {
        return new self(
            week: $array['week'],
            session: $array['session'],
            set: $array['set'],
            data: $array['data'] ?? [],
        );
    }
}
