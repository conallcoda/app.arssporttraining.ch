<?php

namespace App\Models\Training;

use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExercisePlan;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
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
        'group_id',
        'user_id',
        'exercise_program_id',
        'source_plan_id',
        'sort',
    ];

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

    public function slots(): HasMany
    {
        return $this->hasMany(TrainingProgramSlot::class);
    }

    public function scopeForGroup(Builder $query, int $groupId): Builder
    {
        return $query->where('group_id', $groupId)->whereNull('user_id');
    }

    public function scopeForUser(Builder $query, int $groupId, int $userId): Builder
    {
        return $query->where('group_id', $groupId)->where('user_id', $userId);
    }

    public function isGroupLevel(): bool
    {
        return $this->user_id === null;
    }

    public static function findOrCreateOverride(self $groupProgram, int $userId): self
    {
        return static::firstOrCreate(
            [
                'group_id' => $groupProgram->group_id,
                'user_id' => $userId,
                'exercise_program_id' => $groupProgram->exercise_program_id,
            ],
            [
                'source_plan_id' => $groupProgram->source_plan_id,
                'sort' => $groupProgram->sort ?? 0,
            ]
        );
    }

    public static function importFromPlan(ExercisePlan $plan, int $groupId, ?int $userId = null): void
    {
        $maxSort = static::where('group_id', $groupId)
            ->when($userId, fn (Builder $q) => $q->where('user_id', $userId), fn (Builder $q) => $q->whereNull('user_id'))
            ->max('sort') ?? -1;

        foreach ($plan->programs() as $program) {
            $clone = $program->duplicate();
            $maxSort++;

            $trainingProgram = static::create([
                'group_id' => $groupId,
                'user_id' => $userId,
                'exercise_program_id' => $clone->id,
                'source_plan_id' => $plan->id,
                'sort' => $maxSort,
            ]);

            $clone->update([
                'owner_type' => static::class,
                'owner_id' => $trainingProgram->id,
            ]);
        }
    }

    public static function importProgram(ExerciseProgram $program, int $groupId, ?int $userId = null): self
    {
        $clone = $program->duplicate();

        $maxSort = static::where('group_id', $groupId)
            ->when($userId, fn (Builder $q) => $q->where('user_id', $userId), fn (Builder $q) => $q->whereNull('user_id'))
            ->max('sort') ?? -1;

        $trainingProgram = static::create([
            'group_id' => $groupId,
            'user_id' => $userId,
            'exercise_program_id' => $clone->id,
            'sort' => $maxSort + 1,
        ]);

        $clone->update([
            'owner_type' => static::class,
            'owner_id' => $trainingProgram->id,
        ]);

        return $trainingProgram;
    }

    public static function importExercise(Exercise $exercise, int $groupId, ?int $userId = null, ?int $categoryId = null): self
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

        $maxSort = static::where('group_id', $groupId)
            ->when($userId, fn (Builder $q) => $q->where('user_id', $userId), fn (Builder $q) => $q->whereNull('user_id'))
            ->max('sort') ?? -1;

        $trainingProgram = static::create([
            'group_id' => $groupId,
            'user_id' => $userId,
            'exercise_program_id' => $program->id,
            'sort' => $maxSort + 1,
        ]);

        $program->update([
            'owner_type' => static::class,
            'owner_id' => $trainingProgram->id,
        ]);

        return $trainingProgram;
    }
}
