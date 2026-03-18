<?php

namespace App\Data\Training\Notes;

class FocusNoteType extends AbstractNoteType
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
