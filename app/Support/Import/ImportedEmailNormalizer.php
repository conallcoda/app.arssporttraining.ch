<?php

namespace App\Support\Import;

use Illuminate\Support\Str;

class ImportedEmailNormalizer
{
    public static function normalize(?string $email): ?string
    {
        $email = filled(trim((string) $email))
            ? trim((string) $email)
            : null;

        if ($email === null) {
            return null;
        }

        return Str::endsWith(Str::lower($email), '@example.com')
            ? null
            : $email;
    }
}
