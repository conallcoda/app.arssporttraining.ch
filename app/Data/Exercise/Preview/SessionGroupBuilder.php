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
        string $groupingMode = SessionGroupingMode::Week->value,
        ?int $groupSize = null,
    ): array {
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
                    'group' => $groupingMode === SessionGroupingMode::Groups->value
                        ? intdiv($sessionNumber - 1, max(1, (int) ($groupSize ?? 4)))
                        : $week,
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
        string $groupingMode = SessionGroupingMode::Week->value,
        ?int $groupSize = null,
        array $labels = [],
        array $expandedIndexes = [],
        array $lockedSessionsByWeek = [],
        bool $sessionLabels = false,
    ): array {
        $expandedLookup = collect($expandedIndexes)
            ->mapWithKeys(fn (mixed $index) => [(int) $index => true])
            ->all();

        if ($groupingMode === SessionGroupingMode::Groups->value) {
            return [
                'groups' => self::buildFixedGroups($weekCount, $sessionCounts, max(1, (int) ($groupSize ?? 4)), $lockedSessionsByWeek, $sessionLabels),
                'columnLabel' => 'Group',
            ];
        }

        return [
            'groups' => self::buildWeekGroups($weekCount, $sessionCounts, $labels, $expandedLookup, $lockedSessionsByWeek, $sessionLabels),
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
        array $labels,
        array $expandedLookup,
        array $lockedSessionsByWeek,
        bool $sessionLabels,
    ): array {
        $groups = [];
        $sessionNumber = 1;

        for ($week = 0; $week < $weekCount; $week++) {
            $count = max(1, (int) ($sessionCounts[$week] ?? 1));
            $sessions = [];

            for ($session = 0; $session < $count; $session++) {
                $sessions[] = new PreviewGridGroupSession(
                    weekIndex: $week,
                    sessionIndex: $session,
                    sessionNumber: $sessionNumber++,
                    locked: (bool) ($lockedSessionsByWeek[$week][$session] ?? false),
                );
            }

            $groups[] = self::makeGroup(
                index: $week,
                label: (string) ($labels[$week] ?? 'TW'.($week + 1)),
                sessions: $sessions,
                expanded: (bool) ($expandedLookup[$week] ?? false),
                sessionLabels: $sessionLabels,
            );
        }

        return $groups;
    }

    /**
     * @param  array<int, int>  $sessionCounts
     * @param  array<int, array<int, bool>>  $lockedSessionsByWeek
     * @return PreviewGridGroup[]
     */
    private static function buildFixedGroups(
        int $weekCount,
        array $sessionCounts,
        int $groupSize,
        array $lockedSessionsByWeek,
        bool $sessionLabels,
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
                expanded: true,
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

        $collapsedMetaLines = [];
        if ($sessionLabels) {
            $collapsedMetaLines = array_map(
                static fn (int $number): string => __('Session').' '.$number,
                $sessionNumbers,
            );
        } elseif (count($sessions) > 1) {
            $collapsedMetaLines[] = '('.count($sessions).' '.__('sessions').')';
        } else {
            $collapsedMetaLines[] = '(1 '.__('session').')';
        }

        return new PreviewGridGroup(
            index: $index,
            label: $label,
            sessionCount: count($sessions),
            sessions: $sessions,
            sessionRangeLabel: $rangeStart === $rangeEnd ? (string) $rangeStart : $rangeStart.'-'.$rangeEnd,
            expanded: $expanded,
            hasLockedSessions: $hasLockedSessions,
            collapsedMetaLines: $collapsedMetaLines,
            showCopyMenu: false,
        );
    }
}
