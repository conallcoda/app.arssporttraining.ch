<?php

namespace App\Models\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;

interface Plannable
{
    public function programs(): MorphMany;

    public function isTemplate(): bool;
}
