<?php

namespace Coda\Cms\Models\Contracts;

use Illuminate\Database\Eloquent\Model;

interface ResolvesMediaModel
{
    public static function resolveMediaModel(int $id): ?Model;
}
