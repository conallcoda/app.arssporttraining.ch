<?php

namespace App\Data\Training\Blocks;

use App\Form\Fields\Training\Block\Color;

class GenericBlockType extends AbstractBlockType
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

    public static function fields(array $context = []): array
    {
        return [
            Color::make('color'),
        ];
    }
}
