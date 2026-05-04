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

    public static function defaultGroupSize(): int
    {
        return max(1, (int) config('training.session_grouping.default_group_size', 2));
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

        return max(1, (int) ($groupSize ?? self::defaultGroupSize()));
    }

    public static function resolvePreviewSessionCount(array $preview, int $defaultSessionsPerWeek = 1): int
    {
        $mode = self::normalizeMode((string) ($preview['groupingMode'] ?? self::defaultMode()));

        if ($mode === self::None->value) {
            return 1;
        }

        if ($mode === self::Groups->value) {
            return max(1, (int) ($preview['groupSize'] ?? self::defaultGroupSize()));
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

    public static function shouldShowGroupColumn(?string $mode, ?int $groupSize, int $groupCount): bool
    {
        if ($groupCount <= 1) {
            return false;
        }

        $normalizedMode = self::normalizeMode($mode);

        if ($normalizedMode === self::None->value) {
            return false;
        }

        return ! ($normalizedMode === self::Groups->value && self::normalizeGroupSize($groupSize, $normalizedMode) === 1);
    }
}
