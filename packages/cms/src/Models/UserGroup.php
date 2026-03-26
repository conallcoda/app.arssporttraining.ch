<?php

namespace Coda\Cms\Models;

use Coda\Cms\Models\Concerns\HasConfigData;
use Coda\Cms\Models\Concerns\HasOwner;
use Coda\Cms\Models\Concerns\HasQueryBuilder;
use Coda\Cms\Models\Concerns\HasTags;
use Coda\Cms\Models\Concerns\SyncsSortableRelations;
use Coda\Cms\Models\Contracts\Taggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserGroup extends Model implements Taggable
{
    use HasConfigData, HasOwner, HasQueryBuilder, HasTags, SoftDeletes, SyncsSortableRelations;

    protected $fillable = [
        'name',
        'owner_id',
    ];

    public function members(): BelongsToMany
    {
        $userModel = config('cms.models.user', User::class);

        return $this->belongsToMany(
            $userModel,
            'user_group_memberships',
            'user_group_id',
            'user_id'
        )->withPivot('sort')->withTimestamps()->orderByPivot('sort');
    }
}
