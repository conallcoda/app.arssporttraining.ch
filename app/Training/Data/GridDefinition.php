<?php

namespace App\Training\Data;

class GridDefinition
{
    public function __construct(
        /** @var GridRow[] */
        public array $setRows,
        /** @var GridWeekColumn[] */
        public array $weekColumns = [],
    ) {}
}
