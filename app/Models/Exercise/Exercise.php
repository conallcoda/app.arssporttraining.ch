<?php

namespace App\Models\Exercise;

use App\Data\Exercise\ExerciseType;
use App\Models\Concerns\HasConfigData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Exercise extends Model
{
    use HasConfigData;
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
        ];
    }
}
