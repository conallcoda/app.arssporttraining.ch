<?php

namespace Coda\Cms\Models\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphToMany;

interface Taggable
{
    public function tags(): MorphToMany;
}
