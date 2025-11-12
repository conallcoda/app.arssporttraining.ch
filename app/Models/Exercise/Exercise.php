<?php

namespace App\Models\Exercise;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\SchemalessAttributes\Casts\SchemalessAttributes;
use Illuminate\Database\Eloquent\Builder;
use Parental\HasChildren;
use App\Models\Exercise\Types\StrengthExercise;
use App\Models\Exercise\Types\PlyometricExercise;
use App\Models\Exercise\Types\StretchingExercise;
use App\Models\Exercise\Types\CardioExercise;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Awcodes\BadgeableColumn\Components\Badge;
use Filament\Support\Colors\Color;

class Exercise extends Model implements HasMedia
{
    use SoftDeletes;
    use InteractsWithMedia;
    use HasChildren;

    protected $childTypes = [
        'strength' => StrengthExercise::class,
        'plyometric' => PlyometricExercise::class,
        'stretching' => StretchingExercise::class,
        'cardio' => CardioExercise::class,
    ];

    public function getChildTypes(): array
    {
        return $this->childTypes;
    }


    protected $fillable = [
        'name',
        'level',
        'mechanic',
        'instructions',
    ];

    protected $casts = [
        'level' => Level::class,
        'mechanic' => Mechanic::class,
        'instructions' => 'array',
        'extra' => SchemalessAttributes::class,
    ];

    protected function exerciseType(): Attribute
    {
        $reversed = array_flip($this->childTypes);
        $type = $reversed[get_class($this)] ?? 'unknown';
        return Attribute::make(
            get: fn() => $type,
        );
    }

    public static function getBadges(): array
    {
        return [
            Badge::make('type')
                ->label(fn(Exercise $record) => $record->exercise_type)
                ->color(fn(Exercise $record) => match ($record->exercise_type) {
                    'strength' => Color::Blue,
                    'plyometric' => Color::Orange,
                    'stretching' => Color::Green,
                    'cardio' => Color::Red,
                    default => 'gray',
                }),


            Badge::make('level')
                ->label(fn(Exercise $record) => $record->level ? $record->level->value : null)
                ->color(fn(Exercise $record) => match ($record->level) {
                    Level::BEGINNER => Color::Green,
                    Level::INTERMEDIATE => Color::Yellow,
                    Level::EXPERT => Color::Red,
                    default => 'gray',
                })
                ->visible(fn(Exercise $record) => $record->level !== null),

            Badge::make('equipment')
                ->label(fn(Exercise $record) => $record->equipment->pluck('name')->join(', '))
                ->color(Color::Purple)
                ->visible(fn(Exercise $record) => $record->equipment->isNotEmpty()),

            Badge::make('primary_muscles')
                ->label(fn(Exercise $record) => $record->primaryMuscles->pluck('name')->join(', '))
                ->color(Color::Cyan)
                ->visible(fn(Exercise $record) => $record->primaryMuscles->isNotEmpty()),

            Badge::make('secondary_muscles')
                ->label(fn(Exercise $record) => $record->secondaryMuscles->pluck('name')->join(', '))
                ->color(Color::Slate)
                ->visible(fn(Exercise $record) => $record->secondaryMuscles->isNotEmpty()),
        ];
    }


    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')
            ->useDisk('public');
    }

    public function equipment(): BelongsToMany
    {
        return $this->belongsToMany(
            ExerciseEquipment::class,
            'exercise_exercise_equipment',
            'exercise_id',
            'exercise_equipment_id'
        );
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
