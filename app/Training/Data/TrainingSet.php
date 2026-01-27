<?php

namespace App\Training\Data;

use App\Data\AbstractData;

class TrainingSet extends AbstractData
{
    public function __construct(
        public ?int $reps = null,
        public ?float $weight = null,
        public ?float $oneRepMax = null,
    ) {}
}
