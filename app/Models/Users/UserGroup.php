<?php

namespace App\Models\Users;

use App\Models\Concerns\HasExtraData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserGroup extends Model
{
    use HasExtraData, SoftDeletes;

    protected $fillable = [
        'name',
    ];

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'user_group_memberships',
            'user_group_id',
            'user_id'
        )->withPivot('sort')->withTimestamps()->orderByPivot('sort');
    }
}
