<?php

namespace App\Models\Training;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\HasExtraData;
use App\Models\Training\Periods;

class TrainingPeriod extends Model
{
    use SoftDeletes;
    use HasExtraData;
    use HasUuids;

    protected $types = [
        'season' => Periods\TrainingSeason::class,
        'block' => Periods\TrainingBlock::class,
        'week' => Periods\TrainingWeek::class,
        'session' => Periods\TrainingSession::class,
        'exercise' => Periods\TrainingExercise::class,
    ];

    protected $fillable = [
        'uuid',
        'extra',
        'name',
        'type',
        'sequence',
        'parent_id',
    ];

    protected $casts = [
        'sequence' => 'integer',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function parent()
    {
        return $this->belongsTo(TrainingPeriod::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(TrainingPeriod::class, 'parent_id')->orderBy('sequence');
    }

    public function newUniqueId(): string
    {
        return static::createUuid();
    }

    public static function createUuid()
    {
        return (string) \Illuminate\Support\Str::uuid7();
    }

    public static function getExtraConfig(?Model $model = null): array
    {
        return [];
    }
}
