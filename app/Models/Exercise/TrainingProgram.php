<?php

namespace App\Models\Exercise;

use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Coda\Cms\Models\Concerns\HasQueryBuilder;
use Database\Factories\TrainingProgramFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingProgram extends Model
{
    /** @use HasFactory<TrainingProgramFactory> */
    use HasFactory;

    use HasQueryBuilder;
    use SoftDeletes;

    protected static function newFactory(): TrainingProgramFactory
    {
        return TrainingProgramFactory::new();
    }

    protected $table = 'training_programs';

    protected $fillable = [
        'parent_id',
        'group_id',
        'user_id',
        'exercise_program_id',
        'source_plan_id',
        'sort',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(UserGroup::class, 'group_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(ExerciseProgram::class, 'exercise_program_id');
    }

    public function sourcePlan(): BelongsTo
    {
        return $this->belongsTo(ExercisePlan::class, 'source_plan_id');
    }

    public function scopeForGroup(Builder $query, int $groupId): Builder
    {
        return $query->where('group_id', $groupId)->whereNull('user_id');
    }

    public function scopeForUser(Builder $query, int $groupId, int $userId): Builder
    {
        return $query->where('group_id', $groupId)->where('user_id', $userId);
    }

    public static function importFromPlan(ExercisePlan $plan, int $groupId, ?int $userId = null, ?int $parentId = null): void
    {
        $maxSort = static::where('group_id', $groupId)
            ->when($userId, fn (Builder $q) => $q->where('user_id', $userId), fn (Builder $q) => $q->whereNull('user_id'))
            ->max('sort') ?? -1;

        foreach ($plan->programs() as $program) {
            $clone = $program->duplicate();
            $maxSort++;

            static::create([
                'parent_id' => $parentId,
                'group_id' => $groupId,
                'user_id' => $userId,
                'exercise_program_id' => $clone->id,
                'source_plan_id' => $plan->id,
                'sort' => $maxSort,
            ]);
        }
    }

    public static function importProgram(ExerciseProgram $program, int $groupId, ?int $userId = null, ?int $parentId = null): self
    {
        $clone = $program->duplicate();

        $maxSort = static::where('group_id', $groupId)
            ->when($userId, fn (Builder $q) => $q->where('user_id', $userId), fn (Builder $q) => $q->whereNull('user_id'))
            ->max('sort') ?? -1;

        return static::create([
            'parent_id' => $parentId,
            'group_id' => $groupId,
            'user_id' => $userId,
            'exercise_program_id' => $clone->id,
            'sort' => $maxSort + 1,
        ]);
    }

    public static function importExercise(Exercise $exercise, int $groupId, ?int $userId = null, ?int $parentId = null, ?int $categoryId = null): self
    {
        $program = ExerciseProgram::create([
            'name' => $exercise->name,
            'program_category_id' => $categoryId,
        ]);

        ExerciseProgramExercise::create([
            'exercise_program_id' => $program->id,
            'exercise_id' => $exercise->id,
            'sort' => 0,
        ]);

        $maxSort = static::where('group_id', $groupId)
            ->when($userId, fn (Builder $q) => $q->where('user_id', $userId), fn (Builder $q) => $q->whereNull('user_id'))
            ->max('sort') ?? -1;

        return static::create([
            'parent_id' => $parentId,
            'group_id' => $groupId,
            'user_id' => $userId,
            'exercise_program_id' => $program->id,
            'sort' => $maxSort + 1,
        ]);
    }
}
