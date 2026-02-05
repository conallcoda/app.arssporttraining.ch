<?php

namespace App\Cms\Models\Contracts;

use App\Cms\Form\Form;

interface HasForms
{
    public static function getForm(): Form|array;

    public static function getFields(): array;
}
