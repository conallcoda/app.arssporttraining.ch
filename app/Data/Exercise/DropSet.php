<?php

namespace App\Data\Exercise;

class DropSet
{
    public const SET_TYPE_NORMAL = 'normal';

    public const SET_TYPE_DROP = 'drop';

    private const INTEGER_PART = '\d+';

    private const DECIMAL_PART = '\d+(?:\.\d+)?';

    private const DURATION_PART = '(?:\d+|\d{1,3}:[0-5]\d)';

    public static function normalizeSetType(?string $type): string
    {
        return $type === self::SET_TYPE_DROP ? self::SET_TYPE_DROP : self::SET_TYPE_NORMAL;
    }

    public static function isEnabled(array $config = []): bool
    {
        $sets = is_array($config['sets'] ?? null)
            ? $config['sets']
            : (is_array($config['_sets'] ?? null) ? $config['_sets'] : []);

        return self::normalizeSetType($sets['type'] ?? null) === self::SET_TYPE_DROP;
    }

    public static function commaPattern(string $kind): string
    {
        $part = match ($kind) {
            'weight' => self::DECIMAL_PART,
            'duration' => self::DURATION_PART,
            default => self::INTEGER_PART,
        };

        return $part.'(?:,'.$part.')+';
    }

    public static function repsPattern(): string
    {
        return '(?:'.self::commaPattern('reps').'|\d+\s*[xX×]\s*\d+)';
    }

    /**
     * @return list<int>|null
     */
    public static function repsParts(mixed $value): ?array
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if (preg_match('/^(?<count>\d+)\s*[xX×]\s*(?<reps>\d+)$/', $value, $matches)) {
            $count = (int) $matches['count'];
            $reps = (int) $matches['reps'];

            return $count > 1 && $reps >= 0 ? array_fill(0, $count, $reps) : null;
        }

        if (! preg_match('/^'.self::commaPattern('reps').'$/', $value)) {
            return null;
        }

        return array_map('intval', explode(',', $value));
    }

    /**
     * @return list<float>|null
     */
    public static function weightParts(mixed $value): ?array
    {
        if (! is_string($value) || ! preg_match('/^'.self::commaPattern('weight').'$/', trim($value))) {
            return null;
        }

        return array_map('floatval', explode(',', trim($value)));
    }

    public static function normalizeRepsValue(mixed $value): mixed
    {
        $parts = self::repsParts($value);

        return $parts === null ? $value : implode(',', $parts);
    }

    public static function displayReps(array $parts): string
    {
        return implode(',', $parts);
    }

    public static function displayWeight(array $parts): string
    {
        return implode(',', array_map(
            static fn (float $part): string => rtrim(rtrim(number_format($part, 3, '.', ''), '0'), '.'),
            $parts,
        ));
    }

    public static function partCount(string $kind, mixed $value): ?int
    {
        $parts = match ($kind) {
            'reps' => self::repsParts($value),
            'weight' => self::weightParts($value),
            'duration' => self::durationParts($value),
            default => null,
        };

        return $parts === null ? null : count($parts);
    }

    public static function expectedPartCount(array $data): ?int
    {
        $config = is_array($data['config'] ?? null) ? $data['config'] : $data;

        return self::partCount('reps', $config['reps']['default'] ?? null);
    }

    public static function partCountRule(string $kind, ?int $expected): \Closure
    {
        return static function (string $attribute, mixed $value, \Closure $fail) use ($kind, $expected): void {
            if ($value === null || $value === '' || $expected === null) {
                return;
            }

            $actual = self::partCount($kind, $value);

            if ($actual !== null && $actual !== $expected) {
                $fail('The :attribute field must have '.$expected.' drop-set parts.');
            }
        };
    }

    /**
     * @return list<string>|null
     */
    private static function durationParts(mixed $value): ?array
    {
        if (! is_string($value) || ! preg_match('/^'.self::commaPattern('duration').'$/', trim($value))) {
            return null;
        }

        return explode(',', trim($value));
    }
}
