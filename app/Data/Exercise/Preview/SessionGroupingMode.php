<?php

namespace App\Data\Exercise\Preview;

enum SessionGroupingMode: string
{
    case None = 'none';
    case Week = 'week';
    case Groups = 'groups';

    public static function defaultMode(): string
    {
        $mode = (string) config('training.session_grouping.default_mode', self::Groups->value);

        return self::tryFrom($mode)?->value ?? self::Groups->value;
    }

    public static function defaultGroupSize(?string $mode = null): int
    {
        return match (self::normalizeMode($mode)) {
            self::Week->value => min(8, max(1, (int) config('training.session_grouping.default_week_size', 1))),
            self::Groups->value => min(8, max(2, (int) config('training.session_grouping.default_group_size', 2))),
            default => 1,
        };
    }

    public static function defaultCopyValuesAutomatically(): bool
    {
        return (bool) config('training.session_grouping.default_copy_values_automatically', true);
    }

    public static function normalizeMode(?string $mode): string
    {
        return self::tryFrom((string) $mode)?->value ?? self::defaultMode();
    }

    public static function normalizeGroupSize(?int $groupSize, ?string $mode = null): int
    {
        $normalizedMode = self::normalizeMode($mode);

        if ($normalizedMode === self::None->value) {
            return 1;
        }

        $value = (int) ($groupSize ?? self::defaultGroupSize($normalizedMode));

        return match ($normalizedMode) {
            self::Week->value => min(8, max(1, $value)),
            self::Groups->value => min(8, max(2, $value)),
            default => 1,
        };
    }

    public static function normalizeCopyValuesAutomatically(?bool $copyValuesAutomatically, ?string $mode = null): bool
    {
        $normalizedMode = self::normalizeMode($mode);

        if ($normalizedMode === self::None->value) {
            return false;
        }

        return $copyValuesAutomatically ?? self::defaultCopyValuesAutomatically();
    }

    public static function resolvePreviewSessionCount(array $preview, int $defaultSessionsPerWeek = 1): int
    {
        $mode = self::normalizeMode((string) ($preview['groupingMode'] ?? self::defaultMode()));

        if ($mode === self::None->value) {
            return 1;
        }

        if ($mode === self::Groups->value) {
            return max(2, (int) ($preview['groupSize'] ?? self::defaultGroupSize($mode)));
        }

        return max(1, (int) ($preview['sessionsPerWeek'] ?? $defaultSessionsPerWeek));
    }

    public static function options(): array
    {
        return [
            self::None->value => 'None',
            self::Week->value => 'Week',
            self::Groups->value => 'Groups',
        ];
    }

    public static function cycleLabel(?string $mode): string
    {
        return match (self::normalizeMode($mode)) {
            self::Groups->value => 'Groups',
            self::None->value => 'Sessions',
            default => 'Weeks',
        };
    }

    public static function plannedGroupsSuffix(?string $mode): string
    {
        return match (self::normalizeMode($mode)) {
            self::Groups->value => 'group(s)',
            self::None->value => 'session(s)',
            default => 'week(s)',
        };
    }

    public static function sizeFieldLabel(?string $mode): string
    {
        return match (self::normalizeMode($mode)) {
            self::Week->value => 'Week Size',
            self::Groups->value => 'Group Size',
            default => 'Size',
        };
    }

    public static function sizeFieldSuffix(?string $mode): string
    {
        return match (self::normalizeMode($mode)) {
            self::Week->value => 'week(s)',
            self::Groups->value => 'session(s)',
            default => 'session(s)',
        };
    }

    public static function sizeFieldMin(?string $mode): int
    {
        return match (self::normalizeMode($mode)) {
            self::Week->value => 1,
            self::Groups->value => 2,
            default => 1,
        };
    }

    public static function sizeFieldMax(?string $mode): int
    {
        return 8;
    }

    public static function shouldShowGroupColumn(?string $mode, ?int $groupSize, int $groupCount): bool
    {
        $normalizedMode = self::normalizeMode($mode);

        return $normalizedMode !== self::None->value;
    }

    public static function shouldAutoCopyValues(array $preview): bool
    {
        return self::normalizeCopyValuesAutomatically(
            isset($preview['copyValuesAutomatically']) ? (bool) $preview['copyValuesAutomatically'] : null,
            (string) ($preview['groupingMode'] ?? self::defaultMode()),
        );
    }
}
