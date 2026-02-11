<?php

namespace App\Livewire\Test\Data;

enum ExerciseSetting: string
{
    case Distance = 'distance';
    case Duration = 'duration';
    case Pace = 'pace';
    case Reps = 'reps';
    case Rest = 'rest';
    case Tempo = 'tempo';
    case Watts = 'watts';
    case Weight = 'weight';

    /** @return class-string<Settings\AbstractSetting>|null */
    public function settingClass(): ?string
    {
        return match ($this) {
            self::Distance => Settings\DistanceSetting::class,
            self::Duration => Settings\DurationSetting::class,
            self::Pace => Settings\PaceSetting::class,
            self::Reps => Settings\RepsSetting::class,
            self::Rest => Settings\RestSetting::class,
            self::Tempo => Settings\TempoSetting::class,
            self::Watts => Settings\WattsSetting::class,
            self::Weight => Settings\WeightSetting::class,
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
            ->filter(fn (self $case) => $case->hasSetting())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->settingClass()])
            ->all();
    }
}
