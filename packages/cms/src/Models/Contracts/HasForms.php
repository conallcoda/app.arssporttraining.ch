<?php

namespace Coda\Cms\Models\Contracts;

use Coda\Cms\Form\Form;

interface HasForms
{
    public static function getForm(): Form|array;

    public static function getFields(): array;
}
