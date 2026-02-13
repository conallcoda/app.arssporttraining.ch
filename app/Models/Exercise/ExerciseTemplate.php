<?php

namespace App\Models\Exercise;

use App\Cms\Models\Concerns\HasQueryBuilder;
use App\Data\Exercise\ExerciseConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExerciseTemplate extends Model
{
    use HasQueryBuilder;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'config',
    ];

    protected $attributes = [
        'config' => '{"settings":[],"overrides":{"cells":[],"weeks":[]}}',
    ];

    protected function casts(): array
    {
        return [
            'config' => ExerciseConfig::class,
        ];
    }
}
