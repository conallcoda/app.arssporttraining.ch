<?php

namespace App\Models\Exercise;

use App\Data\Exercise\ExerciseConfig;
use Coda\Cms\Models\Concerns\HasOwner;
use Coda\Cms\Models\Concerns\HasQueryBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExerciseTemplate extends Model
{
    use HasOwner;
    use HasQueryBuilder;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'config',
        'owner_id',
    ];

    protected $attributes = [
        'config' => '{"settings":[],"overrides":{"sessions":[],"cells":[]}}',
    ];

    protected function casts(): array
    {
        return [
            'config' => ExerciseConfig::class,
        ];
    }
}
