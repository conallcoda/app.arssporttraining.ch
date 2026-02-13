<?php

namespace App\Data\Exercise\Preview;

interface DefinesEditability
{
    public function isEditable(string $field, int $week, int $set, GridState $state): bool;
}
