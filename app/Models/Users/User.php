<?php

namespace App\Models\Users;

use Coda\Cms\Models\Concerns\HasConfigData;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasConfigData, HasFactory, Notifiable, SoftDeletes;

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
        'config',
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
        return $this->belongsToMany(
            UserGroup::class,
            'user_group_memberships',
            'user_id',
            'user_group_id'
        )->withPivot('sort')->withTimestamps();
    }
}
