<?php

namespace App\Models\Users;

use App\Models\Athlete\MetricSubmission;
use Coda\Cms\Models\User as CmsUser;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class User extends CmsUser
{
    use HasFactory;

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'type' => UserTypeEnum::class,
            'gender' => GenderEnum::class,
        ]);
    }

    public function internalTags(): MorphToMany
    {
        return $this->tagsWithScope('athlete_internal');
    }

    public function metricSubmissions(): HasMany
    {
        return $this->hasMany(MetricSubmission::class, 'user_id');
    }
}
