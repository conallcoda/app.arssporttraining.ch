<?php

namespace Coda\Cms\Form\Concerns;

use Coda\Cms\Form\Form;

trait InteractsWithForms
{
    public static function getForm(): Form|array
    {
        return [];
    }

    public static function getFields(): array
    {
        $form = static::getForm();

        if ($form instanceof Form) {
            return $form->getFields();
        }

        return $form;
    }
}
