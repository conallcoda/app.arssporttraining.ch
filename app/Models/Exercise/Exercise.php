<?php

namespace App\Models\Exercise;

use App\Data\Exercise\ExerciseType;
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
        'extra',
    ];

    protected function casts(): array
    {
        return [
            'type' => ExerciseType::class,
        ];
    }
}
