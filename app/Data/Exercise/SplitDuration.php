<?php

namespace App\Data\Exercise;

use Stringable;

class SplitDuration implements Stringable
{
    /**
     * @param  list<int>  $parts
     */
    public function __construct(
        public array $parts,
        public string $unit = 'seconds',
    ) {}

    public static function parse(mixed $value, string $unit = 'seconds'): ?self
    {
        if (is_int($value)) {
            return new self([$value], $unit);
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $parts = explode('_', $value);

        if (count($parts) > 2) {
            return null;
        }

        $normalized = [];

        foreach ($parts as $part) {
            $seconds = self::normalizePart($part, $unit);

            if ($seconds === null) {
                return null;
            }

            $normalized[] = $seconds;
        }

        return new self($normalized, $unit);
    }

    public static function isValid(mixed $value, string $unit = 'seconds'): bool
    {
        return self::parse($value, $unit) !== null;
    }

    public function isSplit(): bool
    {
        return count($this->parts) === 2;
    }

    public function storageValue(): int|string
    {
        $parts = $this->storageParts();

        if (! $this->isSplit()) {
            return $parts[0];
        }

        return implode('_', $parts);
    }

    public function display(): string
    {
        $formatted = array_map(fn (int $part): string => self::formatPart($part, $this->unit), $this->parts);

        if (! $this->isSplit()) {
            return $formatted[0];
        }

        return $formatted[0].'L_'.$formatted[1].'R';
    }

    public function __toString(): string
    {
        return (string) $this->storageValue();
    }

    private static function normalizePart(string $value, string $unit): ?int
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (str_contains($value, ':')) {
            if (! preg_match('/^(?<minutes>\d{1,3}):(?<seconds>\d{2})$/', $value, $matches)) {
                return null;
            }

            if ((int) $matches['seconds'] > 59) {
                return null;
            }

            return ((int) $matches['minutes'] * 60) + (int) $matches['seconds'];
        }

        if (! preg_match('/^\d+$/', $value)) {
            return null;
        }

        $number = (int) $value;

        return $unit === 'minutes' ? $number * 60 : $number;
    }

    private static function formatPart(int $seconds, string $unit): string
    {
        if ($unit === 'mm:ss') {
            return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
        }

        if ($unit === 'minutes' && $seconds % 60 === 0) {
            return (string) intdiv($seconds, 60);
        }

        return (string) $seconds;
    }

    /**
     * @return list<int>
     */
    private function storageParts(): array
    {
        if ($this->unit !== 'minutes') {
            return $this->parts;
        }

        return array_map(
            fn (int $seconds): int => $seconds % 60 === 0 ? intdiv($seconds, 60) : $seconds,
            $this->parts,
        );
    }
}
