<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgramCategory extends Model
{
    use HasFactory;
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
        return $this->hasMany(TrainingPlanProgram::class);
    }
}
