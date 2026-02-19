<?php

namespace App\Models;

use App\Data\Training\Config\TrainingPlanConfig;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Coda\Cms\Models\Concerns\SyncsSortableRelations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingPlan extends Model
{
    use SoftDeletes;
    use SyncsSortableRelations;

    protected $fillable = [
        'name',
        'config',
    ];

    protected function config(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value
                ? TrainingPlanConfig::from(json_decode($value, true))
                : TrainingPlanConfig::initialize(),
            set: fn (TrainingPlanConfig|array $value) => json_encode(
                $value instanceof TrainingPlanConfig ? $value->toArray() : $value
            ),
        );
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(TrainingPlanUser::class)
            ->withPivot(['sort'])
            ->orderByPivot('sort')
            ->withTimestamps();
    }

    public function userGroups(): BelongsToMany
    {
        return $this->belongsToMany(UserGroup::class)
            ->using(TrainingPlanUserGroup::class)
            ->withPivot(['sort'])
            ->orderByPivot('sort')
            ->withTimestamps();
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
