<?php

namespace App\Models\Training\Factory;

use App\Data\AbstractData;

class SeasonConfig extends AbstractData
{
    public function __construct(
        public string $name = 'New Season',
        public int $numberOfBlocks = 2,
        public int $weeksPerBlock = 5
    ) {}
}
