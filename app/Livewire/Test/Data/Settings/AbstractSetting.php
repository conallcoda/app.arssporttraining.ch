<?php

namespace App\Livewire\Test\Data\Settings;

use App\Cms\Data\AbstractData;
use App\Cms\Form\Concerns\InteractsWithForms;
use App\Cms\Form\Form;
use App\Cms\Models\Contracts\HasForms;

abstract class AbstractSetting extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public static function getName()
    {
        return str_replace('Setting', '', class_basename(static::class));
    }

    public static function fields(): array
    {
        return [];
    }

    public static function getForm(): Form|array
    {
        return Form::make()
            ->fieldset(static::getName(), static::fields());
    }
}
