<?php

namespace App\Models\Contracts;

use App\Form\Form;

interface HasForms
{
    public static function getForm(): Form|array;

    public static function getFields(): array;
}
