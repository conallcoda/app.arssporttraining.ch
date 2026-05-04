<?php

namespace App\Data\Exercise\Preview;

class SessionGroupBuilder
{
    /**
     * @param  array<int, int>  $sessionCounts
     * @return array{
     *     orderedSessions: array<int, array{week:int, session:int, sessionNumber:int, group:int}>,
     *     groupIndexByWeekSession: array<int, array<int, int>>,
     *     sessionNumberByWeekSession: array<int, array<int, int>>,
     *     groupCount: int
     * }
     */
    public static function buildStrategyMap(
        int $weekCount,
        array $sessionCounts,
        ?string $groupingMode = null,
        ?int $groupSize = null,
    ): array {
        $groupingMode = SessionGroupingMode::normalizeMode($groupingMode);
        $effectiveGroupSize = SessionGroupingMode::normalizeGroupSize($groupSize, $groupingMode);
        $usesFixedGroups = in_array($groupingMode, [SessionGroupingMode::Groups->value, SessionGroupingMode::None->value], true);

        $orderedSessions = [];
        $groupIndexByWeekSession = [];
        $sessionNumberByWeekSession = [];
        $sessionNumber = 1;

        for ($week = 0; $week < $weekCount; $week++) {
            $count = max(1, (int) ($sessionCounts[$week] ?? 1));

            for ($session = 0; $session < $count; $session++) {
                $orderedSessions[] = [
                    'week' => $week,
                    'session' => $session,
                    'sessionNumber' => $sessionNumber,
                    'group' => match (true) {
                        $usesFixedGroups => intdiv($sessionNumber - 1, $effectiveGroupSize),
                        $groupingMode === SessionGroupingMode::Week->value => intdiv($week, $effectiveGroupSize),
                        default => $week,
                    },
                ];

                $groupIndexByWeekSession[$week][$session] = $orderedSessions[array_key_last($orderedSessions)]['group'];
                $sessionNumberByWeekSession[$week][$session] = $sessionNumber;
                $sessionNumber++;
            }
        }

        $groupCount = $orderedSessions === []
            ? 0
            : max(array_column($orderedSessions, 'group')) + 1;

        return [
            'orderedSessions' => $orderedSessions,
            'groupIndexByWeekSession' => $groupIndexByWeekSession,
            'sessionNumberByWeekSession' => $sessionNumberByWeekSession,
            'groupCount' => $groupCount,
        ];
    }

    /**
     * @param  array<int, int>  $sessionCounts
     * @param  array<int, string>  $labels
     * @param  int[]  $expandedIndexes
     * @param  array<int, array<int, bool>>  $lockedSessionsByWeek
     * @return array{groups: PreviewGridGroup[], columnLabel: string}
     */
    public static function build(
        int $weekCount,
        array $sessionCounts,
        ?string $groupingMode = null,
        ?int $groupSize = null,
        array $labels = [],
        array $expandedIndexes = [],
        array $lockedSessionsByWeek = [],
        bool $sessionLabels = false,
    ): array {
        $groupingMode = SessionGroupingMode::normalizeMode($groupingMode);
        $effectiveGroupSize = SessionGroupingMode::normalizeGroupSize($groupSize, $groupingMode);

        $expandedLookup = collect($expandedIndexes)
            ->mapWithKeys(fn (mixed $index) => [(int) $index => true])
            ->all();

        if (in_array($groupingMode, [SessionGroupingMode::Groups->value, SessionGroupingMode::None->value], true)) {
            $expandedLookup = collect($expandedIndexes)
                ->mapWithKeys(fn (mixed $index) => [(int) $index => true])
                ->all();

            return [
                'groups' => self::buildFixedGroups($weekCount, $sessionCounts, $effectiveGroupSize, $lockedSessionsByWeek, $sessionLabels, $expandedLookup),
                'columnLabel' => 'Group',
            ];
        }

        return [
            'groups' => self::buildWeekGroups($weekCount, $sessionCounts, $effectiveGroupSize, $labels, $expandedLookup, $lockedSessionsByWeek, $sessionLabels),
            'columnLabel' => 'Week',
        ];
    }

    /**
     * @param  array<int, int>  $sessionCounts
     * @param  array<int, string>  $labels
     * @param  array<int, bool>  $expandedLookup
     * @param  array<int, array<int, bool>>  $lockedSessionsByWeek
     * @return PreviewGridGroup[]
     */
    private static function buildWeekGroups(
        int $weekCount,
        array $sessionCounts,
        int $weekSize,
        array $labels,
        array $expandedLookup,
        array $lockedSessionsByWeek,
        bool $sessionLabels,
    ): array {
        $groups = [];
        $sessionNumber = 1;

        for ($groupIndex = 0, $week = 0; $week < $weekCount; $groupIndex++, $week += $weekSize) {
            $startWeek = $week;
            $endWeek = min($weekCount - 1, $week + $weekSize - 1);
            $sessions = [];

            for ($weekIndex = $startWeek; $weekIndex <= $endWeek; $weekIndex++) {
                $count = max(1, (int) ($sessionCounts[$weekIndex] ?? 1));

                for ($session = 0; $session < $count; $session++) {
                    $sessions[] = new PreviewGridGroupSession(
                        weekIndex: $weekIndex,
                        sessionIndex: $session,
                        sessionNumber: $sessionNumber++,
                        locked: (bool) ($lockedSessionsByWeek[$weekIndex][$session] ?? false),
                    );
                }
            }

            $groups[] = self::makeGroup(
                index: $groupIndex,
                label: self::weekGroupLabel($startWeek, $endWeek, $labels),
                sessions: $sessions,
                expanded: (bool) ($expandedLookup[$groupIndex] ?? false),
                sessionLabels: $sessionLabels,
            );
        }

        return $groups;
    }

    /**
     * @param  array<int, int>  $sessionCounts
     * @param  array<int, array<int, bool>>  $lockedSessionsByWeek
     * @param  array<int, bool>  $expandedLookup
     * @return PreviewGridGroup[]
     */
    private static function buildFixedGroups(
        int $weekCount,
        array $sessionCounts,
        int $groupSize,
        array $lockedSessionsByWeek,
        bool $sessionLabels,
        array $expandedLookup = [],
    ): array {
        $flattened = collect(self::buildStrategyMap(
            weekCount: $weekCount,
            sessionCounts: $sessionCounts,
            groupingMode: SessionGroupingMode::Groups->value,
            groupSize: $groupSize,
        )['orderedSessions'])
            ->map(fn (array $session) => new PreviewGridGroupSession(
                weekIndex: $session['week'],
                sessionIndex: $session['session'],
                sessionNumber: $session['sessionNumber'],
                locked: (bool) ($lockedSessionsByWeek[$session['week']][$session['session']] ?? false),
            ))
            ->all();

        return collect($flattened)
            ->chunk($groupSize)
            ->values()
            ->map(fn ($chunk, $index) => self::makeGroup(
                index: (int) $index,
                label: 'G'.((int) $index + 1),
                sessions: $chunk->values()->all(),
                expanded: (bool) ($expandedLookup[(int) $index] ?? false),
                sessionLabels: $sessionLabels,
            ))
            ->all();
    }

    /**
     * @param  PreviewGridGroupSession[]  $sessions
     */
    private static function makeGroup(
        int $index,
        string $label,
        array $sessions,
        bool $expanded,
        bool $sessionLabels,
    ): PreviewGridGroup {
        $sessionNumbers = array_map(
            static fn (PreviewGridGroupSession $session): int => $session->sessionNumber,
            $sessions,
        );

        $rangeStart = $sessionNumbers[0] ?? 1;
        $rangeEnd = $sessionNumbers === [] ? $rangeStart : $sessionNumbers[array_key_last($sessionNumbers)];
        $hasLockedSessions = collect($sessions)->contains(fn (PreviewGridGroupSession $session): bool => $session->locked);

        return new PreviewGridGroup(
            index: $index,
            label: $label,
            sessionCount: count($sessions),
            sessions: $sessions,
            sessionRangeLabel: $rangeStart === $rangeEnd ? (string) $rangeStart : $rangeStart.'-'.$rangeEnd,
            expanded: $expanded,
            hasLockedSessions: $hasLockedSessions,
            collapsedMetaLines: [],
            showCopyMenu: false,
        );
    }

    private static function weekGroupLabel(int $startWeek, int $endWeek, array $labels): string
    {
        if ($startWeek === $endWeek) {
            return (string) ($labels[$startWeek] ?? 'W'.($startWeek + 1));
        }

        return 'W'.($startWeek + 1).'-W'.($endWeek + 1);
    }
}
