<?php

namespace App\Models\Exercise;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExerciseMuscle extends Model
{
    use SoftDeletes;

    protected $table = 'exercise_muscles';

    protected $fillable = [
        'name',
    ];

    public $timestamps = true;
}
