<?php

namespace App\Data\Training\Notes;

use App\Form\Fields\Training\Note\Color;

class GenericNoteType extends AbstractNoteType
{
    public function __construct(
        public string $color = 'slate',
    ) {}

    public static function defaultColor(): string
    {
        return 'slate';
    }

    public static function label(): string
    {
        return __('Note');
    }

    public static function fields(): array
    {
        return [
            Color::make('color'),
        ];
    }
}
