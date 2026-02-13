<?php

namespace App\Data\Training\Config\Cell;

use App\Cms\Data\AbstractConfig;

class CellOverride extends AbstractConfig
{
    public function __construct(
        public int $week,
        public int $session,
        public int $set,
        public CellOverrideData $data,
    ) {}
}
