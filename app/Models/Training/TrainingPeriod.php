<?php

namespace App\Models\Training;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\HasExtraData;
use App\Models\Training\Periods;

class TrainingPeriod extends Model
{
    use SoftDeletes;
    use HasExtraData;

    protected $types = [
        'season' => Periods\TrainingSeason::class,
        'block' => Periods\TrainingBlock::class,
        'week' => Periods\TrainingWeek::class,
        'session' => Periods\TrainingSession::class,
        'exercise' => Periods\TrainingExercise::class,
    ];

    protected $fillable = [
        'extra',
        'name',
        'type',
        'sequence',
        'parent_id',
    ];

    protected $casts = [
        'sequence' => 'integer',
    ];

    public function parent()
    {
        return $this->belongsTo(TrainingPeriod::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(TrainingPeriod::class, 'parent_id')->orderBy('sequence');
    }

    public static function getExtraConfig(?Model $model = null): array
    {
        return [];
    }
}
