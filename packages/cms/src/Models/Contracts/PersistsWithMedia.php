<?php

namespace Coda\Cms\Models\Contracts;

use Illuminate\Database\Eloquent\Model;

interface PersistsWithMedia
{
    public function persist(): Model;

    public static function resolveModel(int $id): ?Model;
}
