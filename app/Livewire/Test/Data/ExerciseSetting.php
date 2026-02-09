<?php

namespace App\Livewire\Test\Data;

use App\Livewire\Test\Data\Settings;

enum ExerciseSetting: string
{
    case Reps = 'reps';
    case Weight = 'weight';
    case Duration = 'duration';
    case Distance = 'distance';
    case Rest = 'rest';
    case Tempo = 'tempo';
    case Pace = 'pace';
    case Watts = 'watts';

    /** @return class-string<Settings\AbstractSetting>|null */
    public function settingClass(): ?string
    {
        return match ($this) {
            self::Weight => Settings\WeightSetting::class,
            self::Reps => Settings\RepsSetting::class,
            default => null,
        };
    }

    public function hasSetting(): bool
    {
        return $this->settingClass() !== null;
    }

    /** @return array<string, class-string<Settings\AbstractSetting>> */
    public static function settingMap(): array
    {
        return collect(self::cases())
            ->filter(fn(self $case) => $case->hasSetting())
            ->mapWithKeys(fn(self $case) => [$case->value => $case->settingClass()])
            ->all();
    }
}
