<?php

namespace App\Models\Exercise;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExerciseEquipment extends Model
{
    use SoftDeletes;

    protected $table = 'exercise_equipment';

    protected $fillable = [
        'name',
    ];

    public $timestamps = true;
}
