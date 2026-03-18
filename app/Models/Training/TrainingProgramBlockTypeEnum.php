<?php

namespace App\Models\Training;

use App\Data\Training\Blocks\AbstractBlockType;
use App\Data\Training\Blocks\CategoryBlockType;
use App\Data\Training\Blocks\FocusBlockType;
use App\Data\Training\Blocks\GenericBlockType;

enum TrainingProgramBlockTypeEnum: string
{
    case Focus = 'focus';
    case Note = 'note';
    case Category = 'category';

    public function label(): string
    {
        return $this->blockTypeClass()::label();
    }

    /** @return class-string<AbstractBlockType> */
    public function blockTypeClass(): string
    {
        return match ($this) {
            self::Focus => FocusBlockType::class,
            self::Note => GenericBlockType::class,
            self::Category => CategoryBlockType::class,
        };
    }

    /** @return array<string, class-string<AbstractBlockType>> */
    public static function blockTypeMap(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->blockTypeClass()])
            ->all();
    }
}
