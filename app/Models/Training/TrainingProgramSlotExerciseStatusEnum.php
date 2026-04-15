<?php

namespace App\Models\Training;

enum TrainingProgramSlotExerciseStatusEnum: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case PartiallyCompleted = 'partially_completed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Completed => 'Completed',
            self::PartiallyCompleted => 'Partially Completed',
            self::Skipped => 'Skipped',
        };
    }

    /**
     * @return array{light: string, dark: string}
     */
    public function barColor(): array
    {
        return match ($this) {
            self::Completed => ['light' => '110 231 183', 'dark' => '52 211 153'],
            self::PartiallyCompleted => ['light' => '252 211 77', 'dark' => '251 191 36'],
            self::Skipped => ['light' => '125 211 252', 'dark' => '56 189 248'],
            self::Pending => ['light' => '228 228 231', 'dark' => '161 161 170'],
        };
    }

    public function isSubmitted(): bool
    {
        return match ($this) {
            self::Completed, self::PartiallyCompleted, self::Skipped => true,
            self::Pending => false,
        };
    }
}
