<?php

namespace App\Data\Exercise\Preview;

enum SessionGroupingMode: string
{
    case Week = 'week';
    case Groups = 'groups';

    public static function options(): array
    {
        return [
            self::Week->value => 'Week',
            self::Groups->value => 'Groups',
        ];
    }
}
