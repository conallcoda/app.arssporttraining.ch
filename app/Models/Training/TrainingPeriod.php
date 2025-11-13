<?php

namespace App\Models\Training;

use Illuminate\Database\Eloquent\Model;
use Parental\HasChildren;
use Kalnoy\Nestedset\NodeTrait;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\HasExtraData;
use App\Models\Training\Periods;

class TrainingPeriod extends Model
{
    use HasChildren;
    use NodeTrait;
    use SoftDeletes;
    use HasExtraData;

    protected $childTypes = [
        'season' => Periods\TrainingSeason::class,
        'block' => Periods\TrainingBlock::class,
        'week' => Periods\TrainingWeek::class,
        'session' => Periods\TrainingSession::class,
        'exercise' => Periods\TrainingExercise::class,
    ];
    protected $fillable = [
        'name',
        'sequence',
    ];

    protected $casts = [
        'sequence' => 'integer',
    ];

    public static function getExtraConfig(?Model $model = null): array
    {
        return [];
    }
}
