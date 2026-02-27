<?php

namespace App\Models;

use Coda\Cms\Models\Concerns\HasQueryBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgramCategory extends Model
{
    use HasFactory;
    use HasQueryBuilder;
    use SoftDeletes;

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
        return $this->hasMany(ExerciseProgram::class);
    }
}
