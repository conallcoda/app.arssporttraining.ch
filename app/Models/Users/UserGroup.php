<?php

namespace App\Models\Users;

use Coda\Cms\Models\Concerns\HasConfigData;
use Coda\Cms\Models\Concerns\SyncsSortableRelations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserGroup extends Model
{
    use HasConfigData, SoftDeletes, SyncsSortableRelations;

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
