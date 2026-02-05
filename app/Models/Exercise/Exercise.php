<?php

namespace App\Models\Exercise;

use App\Data\Exercise\ExerciseConfig;
use App\Data\Exercise\ExerciseType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Exercise extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'type' => ExerciseType::class,
            'config' => ExerciseConfig::class,
        ];
    }
}
