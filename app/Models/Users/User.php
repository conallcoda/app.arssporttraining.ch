<?php

namespace App\Models\Users;


use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use App\Models\Concerns\HasExtraData;
use Parental\HasChildren;
use App\Models\Users\Types\Admin;
use App\Models\Users\Types\Coach;
use App\Models\Users\Types\Athlete;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasExtraData, HasChildren, SoftDeletes;

    protected $childTypes = [
        'admin' => Admin::class,
        'coach' => Coach::class,
        'athlete' => Athlete::class,
    ];


    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    protected $fillable = [
        'forename',
        'surname',
        'email',
        'phone',
        'password',
        'extra',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public static function getMetricTypes(): bool|array
    {
        return false;
    }

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
            ->map(fn($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return in_array($this->type, ['admin', 'coach']);
    }

    public static function getExtraConfig(?Model $model = null): array
    {
        return [];
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(
            UserGroup::class,
            'user_group_memberships',
            'user_id',
            'user_group_id'
        );
    }

    public function allowedGroupTypes(): array
    {
        return [];
    }

    public function canJoinGroup(UserGroup $group): bool
    {
        return in_array($group->type, $this->allowedGroupTypes());
    }
}
