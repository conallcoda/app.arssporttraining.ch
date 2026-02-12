<?php

namespace App\Livewire\Test\Data\Preview;

interface DefinesEditability
{
    public function isEditable(string $field, int $week, int $set, GridState $state): bool;
}
