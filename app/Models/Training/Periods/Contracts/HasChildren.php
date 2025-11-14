<?php

namespace App\Models\Training\Periods\Contracts;

interface HasChildren
{
    public static function addChildForm(): array;
}
