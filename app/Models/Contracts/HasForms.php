<?php

namespace App\Models\Contracts;

interface HasForms
{
    public static function getFields(): array;
}
