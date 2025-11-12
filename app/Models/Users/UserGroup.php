<?php

namespace App\Models\Users;

use App\Models\Concerns\HasExtraData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Parental\HasChildren;

class UserGroup extends Model
{
    use HasFactory, HasExtraData, HasChildren;

    protected $fillable = [
        'name',
        'type',
    ];

    protected $childTypes = [
        'athlete' => \App\Models\Users\Groups\AthleteGroup::class,
    ];

    public function getChildTypes(): array
    {
        return $this->childTypes;
    }

    public static function getExtraConfig(?Model $model = null): array
    {
        return [];
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'user_group_memberships',
            'user_group_id',
            'user_id'
        );
    }
}
