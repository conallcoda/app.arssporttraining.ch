<?php

namespace App\Data\Training\Config\Cell;

use App\Cms\Data\AbstractConfig;
use Spatie\LaravelData\Optional;

class CellOverrideData extends AbstractConfig
{
    public function __construct(
        public int|Optional $reps,
        public float|Optional $weight,
    ) {}
}
