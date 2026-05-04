<?php

namespace App\Models\Users;

enum AccountSetupStatus: string
{
    case EmailMissing = 'email_missing';
    case SetupEmailNotSent = 'setup_email_not_sent';
    case SetupEmailSent = 'setup_email_sent';
    case SetupEmailExpired = 'setup_email_expired';
    case Active = 'active';

    public function label(): string
    {
        return match ($this) {
            self::EmailMissing => 'Email Missing',
            self::SetupEmailNotSent => 'Setup Email Not Sent',
            self::SetupEmailSent => 'Setup Email Sent',
            self::SetupEmailExpired => 'Setup Email Expired',
            self::Active => 'Active',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::EmailMissing => 'red',
            self::SetupEmailNotSent => 'zinc',
            self::SetupEmailSent => 'amber',
            self::SetupEmailExpired => 'orange',
            self::Active => 'green',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])
            ->all();
    }
}
