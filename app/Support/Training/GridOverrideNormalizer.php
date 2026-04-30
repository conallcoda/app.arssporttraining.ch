<?php

namespace App\Support\Training;

use App\Data\Exercise\ExerciseSetting;

class GridOverrideNormalizer
{
    /** @return array{sessions: array<int, array{week: int, session: int, data: array<string, mixed>}>, cells: array<int, array{week: int, session: int, set: int, data: array<string, mixed>}>} */
    public static function normalize(array $overrides, ?array $config = null, ?array $weekSessionCounts = null): array
    {
        $normalized = [
            'sessions' => self::normalizeSessionEntries($overrides['sessions'] ?? []),
            'cells' => self::normalizeCellEntries($overrides['cells'] ?? []),
        ];

        $legacyWeeks = $overrides['weeks'] ?? [];

        if ($legacyWeeks === []) {
            return $normalized;
        }

        foreach ($legacyWeeks as $weekOverride) {
            if (! is_array($weekOverride)) {
                continue;
            }

            $week = (int) ($weekOverride['week'] ?? 0);
            $data = is_array($weekOverride['data'] ?? null) ? $weekOverride['data'] : [];
            $sessionCount = self::resolveWeekSessionCount($config, $week, $weekSessionCounts);

            for ($session = 0; $session < $sessionCount; $session++) {
                foreach ($data as $field => $value) {
                    if ($field === 'sets') {
                        $normalized['sessions'] = self::putSessionValue(
                            $normalized['sessions'],
                            $week,
                            $session,
                            $field,
                            $value,
                        );

                        continue;
                    }

                    if (self::isSessionLevelField($field)) {
                        $normalized['sessions'] = self::putSessionValue(
                            $normalized['sessions'],
                            $week,
                            $session,
                            $field,
                            $value,
                        );

                        continue;
                    }

                    $normalized['cells'] = self::putCellValue(
                        $normalized['cells'],
                        $week,
                        $session,
                        0,
                        $field,
                        $value,
                    );
                }
            }
        }

        return [
            'sessions' => array_values($normalized['sessions']),
            'cells' => array_values($normalized['cells']),
        ];
    }

    /** @param array<int, mixed> $entries
     *  @return array<int, array{week: int, session: int, data: array<string, mixed>}>
     */
    private static function normalizeSessionEntries(array $entries): array
    {
        $normalized = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $week = (int) ($entry['week'] ?? 0);
            $session = (int) ($entry['session'] ?? 0);
            $data = is_array($entry['data'] ?? null) ? $entry['data'] : [];

            foreach ($data as $field => $value) {
                $normalized = self::putSessionValue($normalized, $week, $session, $field, $value);
            }
        }

        return array_values($normalized);
    }

    /** @param array<int, mixed> $entries
     *  @return array<int, array{week: int, session: int, set: int, data: array<string, mixed>}>
     */
    private static function normalizeCellEntries(array $entries): array
    {
        $normalized = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $week = (int) ($entry['week'] ?? 0);
            $session = (int) ($entry['session'] ?? 0);
            $set = (int) ($entry['set'] ?? 0);
            $data = is_array($entry['data'] ?? null) ? $entry['data'] : [];

            foreach ($data as $field => $value) {
                $normalized = self::putCellValue($normalized, $week, $session, $set, $field, $value);
            }
        }

        return array_values($normalized);
    }

    /** @param array<int, array{week: int, session: int, data: array<string, mixed>}> $entries
     *  @return array<int, array{week: int, session: int, data: array<string, mixed>}>
     */
    public static function putSessionValue(array $entries, int $week, int $session, string $field, mixed $value): array
    {
        foreach ($entries as $index => $entry) {
            if (($entry['week'] ?? null) === $week && ($entry['session'] ?? null) === $session) {
                $entries[$index]['data'][$field] = $value;

                return $entries;
            }
        }

        $entries[] = [
            'week' => $week,
            'session' => $session,
            'data' => [$field => $value],
        ];

        return $entries;
    }

    /** @param array<int, array{week: int, session: int, set: int, data: array<string, mixed>}> $entries
     *  @return array<int, array{week: int, session: int, set: int, data: array<string, mixed>}>
     */
    public static function putCellValue(array $entries, int $week, int $session, int $set, string $field, mixed $value): array
    {
        foreach ($entries as $index => $entry) {
            if (($entry['week'] ?? null) === $week
                && ($entry['session'] ?? null) === $session
                && ($entry['set'] ?? null) === $set) {
                $entries[$index]['data'][$field] = $value;

                return $entries;
            }
        }

        $entries[] = [
            'week' => $week,
            'session' => $session,
            'set' => $set,
            'data' => [$field => $value],
        ];

        return $entries;
    }

    private static function isSessionLevelField(string $field): bool
    {
        return in_array($field, ['sets', ExerciseSetting::Rest->value, ExerciseSetting::Tempo->value], true);
    }

    private static function resolveWeekSessionCount(?array $config, int $week, ?array $weekSessionCounts): int
    {
        $count = (int) ($weekSessionCounts[$week] ?? 0);

        if ($count > 0) {
            return $count;
        }

        $previewSessions = (int) ($config['preview']['sessionsPerWeek'] ?? 1);

        return max($previewSessions, 1);
    }
}
