<?php

namespace App\Models\Exercise;

use App\Models\Concerns\HasExtraData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Exercise extends Model
{
    use HasExtraData;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'type',
    ];

    public static function getExtraConfig(?Model $model = null): array
    {
        return [];
    }
}
