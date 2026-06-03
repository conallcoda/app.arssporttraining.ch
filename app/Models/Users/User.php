<?php

namespace App\Models\Users;

use App\Models\Athlete\MetricSubmission;
use Coda\Cms\Models\User as CmsUser;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class User extends CmsUser
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            if (blank($user->account_setup_uuid)) {
                $user->account_setup_uuid = (string) Str::uuid();
            }
        });
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'type' => UserTypeEnum::class,
            'gender' => GenderEnum::class,
            'account_setup_sent_at' => 'datetime',
            'account_setup_expires_at' => 'datetime',
            'account_setup_completed_at' => 'datetime',
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

    public function accountSetupStatus(): AccountSetupStatus
    {
        if (! $this->hasSetupEmail()) {
            return AccountSetupStatus::EmailMissing;
        }

        if ($this->account_setup_completed_at !== null) {
            return AccountSetupStatus::Active;
        }

        if ($this->account_setup_sent_at !== null && $this->accountSetupHasExpired()) {
            return AccountSetupStatus::SetupEmailExpired;
        }

        if ($this->account_setup_sent_at !== null) {
            return AccountSetupStatus::SetupEmailSent;
        }

        return AccountSetupStatus::SetupEmailNotSent;
    }

    public function hasSetupEmail(): bool
    {
        return filled(trim((string) $this->email));
    }

    public function accountSetupHasExpired(): bool
    {
        return $this->account_setup_expires_at !== null
            && $this->account_setup_expires_at->isPast();
    }

    public function issueAccountSetupToken(): string
    {
        $token = Str::random((int) config('user.account_setup_token_length', 64));
        $now = now();

        $this->forceFill([
            'account_setup_uuid' => $this->account_setup_uuid ?: (string) Str::uuid(),
            'account_setup_token_hash' => hash('sha256', $token),
            'account_setup_sent_at' => $now,
            'account_setup_expires_at' => $now->copy()->addDays((int) config('user.account_setup_expiry_days', 30)),
            'account_setup_completed_at' => null,
        ])->save();

        return $token;
    }

    public function completeAccountSetup(string $password): void
    {
        $this->forceFill([
            'password' => $password,
            'account_setup_token_hash' => null,
            'account_setup_expires_at' => null,
            'account_setup_completed_at' => now(),
        ])->save();
    }

    public function hasValidAccountSetupToken(string $token): bool
    {
        if ($this->account_setup_token_hash === null || $this->accountSetupHasExpired()) {
            return false;
        }

        return hash_equals($this->account_setup_token_hash, hash('sha256', $token));
    }

    public function invalidatePendingAccountSetupIfEmailChanged(?string $originalEmail, ?string $updatedEmail): void
    {
        if ($this->account_setup_completed_at !== null) {
            return;
        }

        $normalize = static fn (?string $email): ?string => filled(trim((string) $email))
            ? Str::lower(trim((string) $email))
            : null;

        if ($normalize($originalEmail) === $normalize($updatedEmail)) {
            return;
        }

        $this->forceFill([
            'account_setup_token_hash' => null,
            'account_setup_sent_at' => null,
            'account_setup_expires_at' => null,
        ])->save();
    }

    public function scopeForAccountSetupStatus(Builder $query, string|AccountSetupStatus $status): Builder
    {
        $status = is_string($status) ? AccountSetupStatus::from($status) : $status;
        $now = Carbon::now();

        return match ($status) {
            AccountSetupStatus::EmailMissing => $query->where(fn (Builder $q) => $q
                ->whereNull('email')
                ->orWhere('email', '')),
            AccountSetupStatus::Active => $query
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->whereNotNull('account_setup_completed_at'),
            AccountSetupStatus::SetupEmailExpired => $query
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->whereNull('account_setup_completed_at')
                ->whereNotNull('account_setup_sent_at')
                ->whereNotNull('account_setup_expires_at')
                ->where('account_setup_expires_at', '<', $now),
            AccountSetupStatus::SetupEmailSent => $query
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->whereNull('account_setup_completed_at')
                ->whereNotNull('account_setup_sent_at')
                ->where(function (Builder $q) use ($now): void {
                    $q->whereNull('account_setup_expires_at')
                        ->orWhere('account_setup_expires_at', '>=', $now);
                }),
            AccountSetupStatus::SetupEmailNotSent => $query
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->whereNull('account_setup_completed_at')
                ->whereNull('account_setup_sent_at'),
        };
    }
}
