<?php

namespace App\Data\Coach\Settings;

use Coda\Cms\Data\AbstractData;
use Coda\FormKit\Concerns\InteractsWithForms;
use Coda\FormKit\Contracts\HasForms;
use Coda\FormKit\Form;
use Illuminate\Support\Str;

abstract class AbstractCoachSetting extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public static function getName(): string
    {
        $name = str_replace('Setting', '', class_basename(static::class));

        return preg_replace('/([a-z])([A-Z])/', '$1 $2', $name);
    }

    public static function fields(array $data = []): array
    {
        return [];
    }

    public static function fieldsetKey(): string
    {
        return Str::snake(static::getName());
    }

    public static function getForm(): Form
    {
        return Form::make()
            ->fieldset(static::getName(), static::fields());
    }
}
