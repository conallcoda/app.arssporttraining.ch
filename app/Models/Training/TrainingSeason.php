<?php

namespace App\Models\Training;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Users\User;
use App\Models\Users\Groups\AthleteGroup;

class TrainingSeason extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name'
    ];

    public function blocks(): HasMany
    {
        return $this->hasMany(TrainingBlock::class);
    }

    public function athletes(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'training_season_athletes',
            'training_season_id',
            'athlete_id'
        );
    }

    public function athleteGroups(): BelongsToMany
    {
        return $this->belongsToMany(
            AthleteGroup::class,
            'training_season_athlete_groups',
            'training_season_id',
            'athlete_group_id'
        );
    }
}
