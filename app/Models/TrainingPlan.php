<?php

namespace App\Models;

use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingPlan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'start_date',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function userGroups(): BelongsToMany
    {
        return $this->belongsToMany(UserGroup::class)->withTimestamps();
    }

    public function programs(): HasMany
    {
        return $this->hasMany(TrainingPlanProgram::class);
    }

    public function allUsers(): Builder
    {
        return User::query()
            ->where(function (Builder $query) {
                $query->whereIn('id', $this->users()->select('users.id'))
                    ->orWhereIn('id', function ($sub) {
                        $sub->select('user_id')
                            ->from('user_group_memberships')
                            ->whereIn('user_group_id', $this->userGroups()->select('user_groups.id'));
                    });
            });
    }
}
