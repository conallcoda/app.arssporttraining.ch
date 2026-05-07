<?php

namespace App\Models\Training;

use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Users\UserGroup;
use Carbon\Carbon;
use Coda\Cms\Models\Concerns\HasOwner;
use Coda\Cms\Models\Concerns\HasQueryBuilder;
use Database\Factories\TrainingProgramFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingProgram extends Model
{
    /** @use HasFactory<TrainingProgramFactory> */
    use HasFactory;

    use HasOwner;
    use HasQueryBuilder;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected static function newFactory(): TrainingProgramFactory
    {
        return TrainingProgramFactory::new();
    }

    protected $table = 'training_programs';

    protected $fillable = [
        'group_id',
        'exercise_program_id',
        'sort',
        'status',
        'owner_id',
    ];

    public static function statusOptions(): array
    {
        return [
            self::STATUS_ACTIVE => __('Active'),
            self::STATUS_ARCHIVED => __('Archived'),
        ];
    }

    public static function normalizeStatus(?string $status): ?string
    {
        return $status === self::STATUS_ARCHIVED ? self::STATUS_ARCHIVED : null;
    }

    public function statusValue(): string
    {
        return $this->status === self::STATUS_ARCHIVED
            ? self::STATUS_ARCHIVED
            : self::STATUS_ACTIVE;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    public function scopeVisibleInDateRange(Builder $query, int $groupId, Carbon $start, Carbon $end, ?int $userId = null): Builder
    {
        return $query
            ->where('group_id', $groupId)
            ->where(function (Builder $query) use ($start, $end, $userId): void {
                $query
                    ->whereNull('status')
                    ->orWhere('status', '!=', self::STATUS_ARCHIVED)
                    ->orWhereHas('slots', function (Builder $slotQuery) use ($start, $end, $userId): void {
                        $slotQuery->whereBetween('datetime', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);

                        if ($userId !== null) {
                            $slotQuery->where('user_id', $userId);
                        }
                    });
            });
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(UserGroup::class, 'group_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(ExerciseProgram::class, 'exercise_program_id');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(TrainingProgramSlot::class);
    }

    public static function importProgram(ExerciseProgram $program, int $groupId): self
    {
        $clone = $program->duplicate();

        $maxSort = static::where('group_id', $groupId)->max('sort') ?? -1;

        $trainingProgram = static::create([
            'group_id' => $groupId,
            'exercise_program_id' => $clone->id,
            'sort' => $maxSort + 1,
            'status' => null,
        ]);

        $clone->update([
            'parent_type' => static::class,
            'parent_id' => $trainingProgram->id,
        ]);

        return $trainingProgram;
    }

    public static function importExercise(Exercise $exercise, int $groupId, ?int $categoryId = null): self
    {
        $program = ExerciseProgram::create([
            'name' => $exercise->name,
            'exercise_category_id' => $categoryId,
        ]);

        ExerciseProgramExercise::create([
            'exercise_program_id' => $program->id,
            'exercise_id' => $exercise->id,
            'sort' => 0,
        ]);

        $maxSort = static::where('group_id', $groupId)->max('sort') ?? -1;

        $trainingProgram = static::create([
            'group_id' => $groupId,
            'exercise_program_id' => $program->id,
            'sort' => $maxSort + 1,
            'status' => null,
        ]);

        $program->update([
            'parent_type' => static::class,
            'parent_id' => $trainingProgram->id,
        ]);

        return $trainingProgram;
    }
}
