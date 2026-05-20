<?php

namespace App\Models\Exercise;

enum ExerciseProgramTypeEnum: string
{
    case Program = 'program';
    case WarmUp = 'warm_up';
    case WarmDown = 'warm_down';

    public function label(): string
    {
        return match ($this) {
            self::Program => 'Program',
            self::WarmUp => 'Warm Up',
            self::WarmDown => 'Cool Down',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
