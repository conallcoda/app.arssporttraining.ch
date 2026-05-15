<?php

namespace App\Data\Training\Audit;

final readonly class ScheduledSnapshotMismatchData
{
    public function __construct(
        public string $path,
        public string $kind,
        public mixed $expected,
        public mixed $actual,
    ) {}
}
