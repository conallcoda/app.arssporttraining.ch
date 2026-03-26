<?php

namespace App\Models\Users;

use Coda\Cms\Models\UserGroup as CmsUserGroup;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class UserGroup extends CmsUserGroup
{
    public function internalTags(): MorphToMany
    {
        return $this->tagsWithScope('athlete_group_internal');
    }
}
