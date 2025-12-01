<?php

namespace App\Models\Training\Progression\Override;

use App\Data\AbstractData;
use Carbon\Carbon;

class SetOverride extends AbstractData
{
    public function __construct(
        public ?int $reps = null,
        public ?float $weight = null,
        public bool $locked = false,
        public ?Carbon $lockedAt = null,
    ) {}

    public function hasRepsOverride(): bool
    {
        return $this->reps !== null;
    }

    public function hasWeightOverride(): bool
    {
        return $this->weight !== null;
    }

    public function hasAnyOverride(): bool
    {
        return $this->hasRepsOverride() || $this->hasWeightOverride();
    }

    public function lock(): static
    {
        $this->locked = true;
        $this->lockedAt = Carbon::now();

        return $this;
    }

    public function unlock(): static
    {
        $this->locked = false;
        $this->lockedAt = null;

        return $this;
    }
}
