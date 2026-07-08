<?php

namespace Coda\FormKit\Contracts;

use Coda\FormKit\Form;

interface HasForms
{
    public static function getForm(): Form|array;

    public static function getFields(): array;
}
