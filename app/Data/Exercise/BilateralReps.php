<?php

namespace App\Data\Exercise;

use Stringable;

class BilateralReps implements Stringable
{
    public function __construct(
        public int $left,
        public ?int $right = null,
    ) {}

    public static function parse(string|int $value): self
    {
        if (is_int($value)) {
            return new self($value);
        }

        $value = trim($value);

        if (str_contains($value, '_')) {
            $parts = explode('_', $value, 2);

            return new self((int) $parts[0], (int) $parts[1]);
        }

        return new self((int) $value);
    }

    public static function isValid(string $value): bool
    {
        return (bool) preg_match('/^\d+(_\d+)?$/', trim($value));
    }

    public function isBilateral(): bool
    {
        return $this->right !== null;
    }

    public function total(): int
    {
        if ($this->right === null) {
            return $this->left;
        }

        return $this->left + $this->right;
    }

    public function decrement(int $amount): self
    {
        if ($this->right === null) {
            return new self($this->left - $amount);
        }

        return new self($this->left - $amount, $this->right - $amount);
    }

    public function increment(int $amount): self
    {
        if ($this->right === null) {
            return new self($this->left + $amount);
        }

        return new self($this->left + $amount, $this->right + $amount);
    }

    public function clampMinimum(int $minimum): self
    {
        if ($this->right === null) {
            return new self(max($this->left, $minimum));
        }

        return new self(max($this->left, $minimum), max($this->right, $minimum));
    }

    public function __toString(): string
    {
        if ($this->right === null) {
            return (string) $this->left;
        }

        return "{$this->left}_{$this->right}";
    }
}
