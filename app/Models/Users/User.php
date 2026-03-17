<?php

namespace App\Models\Users;

use App\Models\Athlete\MetricSubmission;
use App\Models\Concerns\HasOwner;
use Coda\Cms\Models\Concerns\HasConfigData;
use Coda\Cms\Models\Concerns\HasQueryBuilder;
use Coda\Cms\Models\Concerns\HasTags;
use Coda\Cms\Models\Contracts\Taggable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable implements Taggable
{
    use HasConfigData, HasFactory, HasOwner, HasQueryBuilder, HasTags, Notifiable, SoftDeletes;

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
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
        return [
            'type' => UserTypeEnum::class,
            'gender' => GenderEnum::class,
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

    public function internalTags(): MorphToMany
    {
        return $this->tagsWithScope('athlete_internal');
    }

    public function metricSubmissions(): HasMany
    {
        return $this->hasMany(MetricSubmission::class, 'user_id');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(
            UserGroup::class,
            'user_group_memberships',
            'user_id',
            'user_group_id'
        )->withPivot('sort')->withTimestamps();
    }
}
