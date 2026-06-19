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

    public function getTable(): string
    {
        return config('cms.tables.user_group', parent::getTable());
    }

    protected $fillable = [
        'name',
        'owner_id',
    ];

    public function members(): BelongsToMany
    {
        $userModel = config('cms.models.user', User::class);
        $pivotTable = config('cms.tables.user_group_membership', 'user_group_memberships');
        $userForeignKey = config('cms.columns.user_foreign_key', 'user_id');
        $userGroupForeignKey = config('cms.columns.user_group_foreign_key', 'user_group_id');

        return $this->belongsToMany(
            $userModel,
            $pivotTable,
            $userGroupForeignKey,
            $userForeignKey
        )->withPivot('sort')->withTimestamps()->orderByPivot('sort');
    }
}
