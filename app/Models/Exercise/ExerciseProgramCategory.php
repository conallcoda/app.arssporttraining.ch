<?php

namespace App\Models\Exercise;

use Coda\Cms\Models\Concerns\HasQueryBuilder;
use Database\Factories\ExerciseProgramCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExerciseProgramCategory extends Model
{
    /** @use HasFactory<ExerciseProgramCategoryFactory> */
    use HasFactory;

    use HasQueryBuilder;
    use SoftDeletes;

    protected static function newFactory(): ExerciseProgramCategoryFactory
    {
        return ExerciseProgramCategoryFactory::new();
    }

    protected $table = 'exercise_program_categories';

    protected $fillable = [
        'name',
        'color',
        'sort',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'json',
        ];
    }

    public function programs(): HasMany
    {
        return $this->hasMany(ExerciseProgram::class, 'program_category_id');
    }
}
