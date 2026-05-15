<?php

namespace Coda\Cms\Models;

use Illuminate\Database\Eloquent\Relations\MorphPivot;

class TaggablePivot extends MorphPivot
{
    protected $casts = [
        'sort' => 'integer',
        'score' => 'decimal:2',
        'extra' => 'array',
    ];
}
