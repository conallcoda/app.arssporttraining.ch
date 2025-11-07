<?php

namespace App\Models\Exercise;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\SchemalessAttributes\Casts\SchemalessAttributes;
use Illuminate\Database\Eloquent\Builder;

class Exercise extends Model implements HasMedia
{
    use SoftDeletes;
    use InteractsWithMedia;


    protected $fillable = [
        'name',
        'slug',
        'force',
        'level',
        'mechanic',
        'exercise_equipment_id',
        'exercise_category_id',
        'instructions',

    ];

    protected $casts = [
        'force' => Force::class,
        'level' => Level::class,
        'mechanic' => Mechanic::class,
        'instructions' => 'array',
        'extra' => SchemalessAttributes::class,
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')
            ->useDisk('public');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(ExerciseEquipment::class, 'exercise_equipment_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExerciseCategory::class, 'exercise_category_id');
    }

    public function primaryMuscles(): BelongsToMany
    {
        return $this->belongsToMany(
            ExerciseMuscle::class,
            'exercise_primary_muscles',
            'exercise_id',
            'exercise_muscle_id'
        );
    }

    public function secondaryMuscles(): BelongsToMany
    {
        return $this->belongsToMany(
            ExerciseMuscle::class,
            'exercise_secondary_muscles',
            'exercise_id',
            'exercise_muscle_id'
        );
    }

    public function scopeWithExtra(): Builder
    {
        return $this->extra->modelScope();
    }
}
