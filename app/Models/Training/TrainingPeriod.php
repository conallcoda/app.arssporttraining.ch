<?php

namespace App\Models\Training;

use App\Models\Concerns\HasExtraData;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

class TrainingPeriod extends Model
{
    use HasExtraData;
    use HasRecursiveRelationships;
    use HasUuids;
    use SoftDeletes;

    protected $types = [
        'season' => Data\SeasonData::class,
        'block' => Data\BlockData::class,
        'week' => Data\WeekData::class,
        'session' => Data\SessionData::class,
        'exercise' => Data\ExerciseData::class,
        'set' => Data\SetData::class,
    ];

    protected $fillable = [
        'uuid',
        'extra',
        'name',
        'type',
        'sequence',
        'parent_id',
        'linked_to',
    ];

    protected $casts = [
        'sequence' => 'integer',
    ];

    public function toData()
    {
        $dataClass = $this->types[$this->type] ?? null;
        if (! $dataClass) {
            throw new \InvalidArgumentException("Unknown type: {$this->type}");
        }

        return $dataClass::fromModel($this, ['sequence' => $this->sequence]);
    }

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

    public function linkedSource()
    {
        return $this->belongsTo(TrainingPeriod::class, 'linked_to', 'uuid');
    }

    public function newUniqueId(): string
    {
        return static::createUuid();
    }

    public static function createUuid()
    {
        return (string) \Illuminate\Support\Str::uuid7();
    }
}
