<?php

namespace App\Data\Exercise\Strategies\Contracts;

use App\Data\Exercise\Preview\GridState;

interface DefinesEditability
{
    public function isEditable(string $field, int $week, int $set, GridState $state): bool;
}
