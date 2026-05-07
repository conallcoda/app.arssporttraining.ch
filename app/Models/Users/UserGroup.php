<?php

namespace App\Models\Users;

use Coda\Cms\Models\UserGroup as CmsUserGroup;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class UserGroup extends CmsUserGroup
{
    public function members(): BelongsToMany
    {
        return parent::members()
            ->where('users.type', UserTypeEnum::Athlete->value);
    }

    public function internalTags(): MorphToMany
    {
        return $this->tagsWithScope('athlete_group_internal');
    }
}
