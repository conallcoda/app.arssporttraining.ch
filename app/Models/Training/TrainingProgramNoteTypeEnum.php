<?php

namespace App\Models\Training;

use App\Data\Training\Notes\AbstractNoteType;
use App\Data\Training\Notes\FocusNoteType;
use App\Data\Training\Notes\GenericNoteType;

enum TrainingProgramNoteTypeEnum: string
{
    case Focus = 'focus';
    case Note = 'note';

    public function label(): string
    {
        return $this->noteTypeClass()::label();
    }

    /** @return class-string<AbstractNoteType> */
    public function noteTypeClass(): string
    {
        return match ($this) {
            self::Focus => FocusNoteType::class,
            self::Note => GenericNoteType::class,
        };
    }

    /** @return array<string, class-string<AbstractNoteType>> */
    public static function noteTypeMap(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->noteTypeClass()])
            ->all();
    }
}
