<?php

namespace App\Data\Training\Blocks;

class FocusBlockType extends AbstractBlockType
{
    public static function defaultColor(): string
    {
        return 'amber';
    }

    public static function label(): string
    {
        return __('Focus');
    }
}
