<?php

namespace App\Models\Training;

use App\Models\Concerns\HasOwner;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExercisePlan;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramExercise;
use App\Models\Users\UserGroup;
use Coda\Cms\Models\Concerns\HasQueryBuilder;
use Database\Factories\TrainingProgramFactory;
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

    protected static function newFactory(): TrainingProgramFactory
    {
        return TrainingProgramFactory::new();
    }

    protected $table = 'training_programs';

    protected $fillable = [
        'group_id',
        'exercise_program_id',
        'sort',
        'owner_id',
    ];

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

    public static function importFromPlan(ExercisePlan $plan, int $groupId): void
    {
        $maxSort = static::where('group_id', $groupId)->max('sort') ?? -1;

        foreach ($plan->programs() as $program) {
            $clone = $program->duplicate();
            $maxSort++;

            $trainingProgram = static::create([
                'group_id' => $groupId,
                'exercise_program_id' => $clone->id,
                'sort' => $maxSort,
            ]);

            $clone->update([
                'parent_type' => static::class,
                'parent_id' => $trainingProgram->id,
            ]);
        }
    }

    public static function importProgram(ExerciseProgram $program, int $groupId): self
    {
        $clone = $program->duplicate();

        $maxSort = static::where('group_id', $groupId)->max('sort') ?? -1;

        $trainingProgram = static::create([
            'group_id' => $groupId,
            'exercise_program_id' => $clone->id,
            'sort' => $maxSort + 1,
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
        ]);

        $program->update([
            'parent_type' => static::class,
            'parent_id' => $trainingProgram->id,
        ]);

        return $trainingProgram;
    }
}
