<?php

namespace App\Livewire;

use App\Data\AbstractData;

class ItineraryConfig extends AbstractData
{
    public function __construct(
        public int $numberOfBlocks = 2,
        public int $weeksPerBlock = 5,
    ) {}
}
