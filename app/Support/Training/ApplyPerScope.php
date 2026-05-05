<?php

namespace App\Support\Training;

final class ApplyPerScope
{
    public const FORM_SESSION = 'per_session';

    public const FORM_SET = 'per_set';

    public const SESSION = 'session';

    public const SET = 'set';

    public static function normalize(?string $applyPer, string $default = self::SESSION): string
    {
        $value = strtolower(trim((string) $applyPer));

        return match ($value) {
            self::FORM_SESSION => self::SESSION,
            self::FORM_SET => self::SET,
            'week' => self::SESSION,
            'session' => self::SET,
            self::SET => self::SET,
            default => $default,
        };
    }

    public static function toFormValue(?string $applyPer, string $default = self::FORM_SESSION): string
    {
        $value = strtolower(trim((string) $applyPer));

        return match ($value) {
            self::FORM_SESSION, 'week' => self::FORM_SESSION,
            self::FORM_SET, self::SET, self::SESSION => self::FORM_SET,
            default => $default,
        };
    }

    public static function normalizeConfigForComparison(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            if ($key === 'applyPer' && is_string($item)) {
                $normalized[$key] = self::normalize($item, self::SET);

                continue;
            }

            $normalized[$key] = self::normalizeConfigForComparison($item);
        }

        return $normalized;
    }

    public static function prepareConfigForForm(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $prepared = [];

        foreach ($value as $key => $item) {
            if ($key === 'applyPer' && is_string($item)) {
                $prepared[$key] = self::toFormValue($item, self::FORM_SET);

                continue;
            }

            $prepared[$key] = self::prepareConfigForForm($item);
        }

        return $prepared;
    }
}
