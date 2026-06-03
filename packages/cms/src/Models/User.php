<?php

namespace Coda\Cms\Models;

use Coda\Cms\Models\Concerns\HasConfigData;
use Coda\Cms\Models\Concerns\HasOptionalPasskeys;
use Coda\Cms\Models\Concerns\HasOwner;
use Coda\Cms\Models\Concerns\HasQueryBuilder;
use Coda\Cms\Models\Concerns\HasTags;
use Coda\Cms\Models\Contracts\OptionalPasskeyUser;
use Coda\Cms\Models\Contracts\Taggable;
use Coda\Cms\Models\Enums\GenderEnum;
use Coda\Cms\Models\Enums\UserTypeEnum;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable implements OptionalPasskeyUser, Taggable
{
    use HasConfigData, HasOptionalPasskeys, HasOwner, HasQueryBuilder, HasTags, Notifiable, SoftDeletes;

    public function getTable(): string
    {
        return config('cms.tables.user', parent::getTable());
    }

    protected $fillable = [
        'forename',
        'surname',
        'email',
        'type',
        'phone',
        'password',
        'gender',
        'date_of_birth',
        'color',
        'config',
        'owner_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function update(array $attributes = [], array $options = []): bool
    {
        if (isset($attributes['password']) && empty($attributes['password'])) {
            unset($attributes['password']);
        }

        return parent::update($attributes, $options);
    }

    protected function casts(): array
    {
        $userTypeEnum = config('cms.enums.user_type', UserTypeEnum::class);
        $genderEnum = config('cms.enums.gender', GenderEnum::class);

        return [
            'type' => $userTypeEnum,
            'gender' => $genderEnum,
            'date_of_birth' => 'date',
            'password' => 'hashed',
        ];
    }

    public function getNameAttribute(): string
    {
        return trim("{$this->forename} {$this->surname}");
    }

    public function initials(): string
    {
        return Str::of($this->forename)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    public function groups(): BelongsToMany
    {
        $userGroupModel = config('cms.models.user_group', UserGroup::class);
        $pivotTable = config('cms.tables.user_group_membership', 'user_group_memberships');
        $userForeignKey = config('cms.columns.user_foreign_key', 'user_id');
        $userGroupForeignKey = config('cms.columns.user_group_foreign_key', 'user_group_id');

        return $this->belongsToMany(
            $userGroupModel,
            $pivotTable,
            $userForeignKey,
            $userGroupForeignKey
        )->withPivot('sort')->withTimestamps();
    }
}
