<?php

namespace App\Data\Athlete\Metric;

use Coda\Cms\Data\AbstractData;
use Coda\Cms\Form\Concerns\InteractsWithForms;
use Coda\Cms\Form\Form;
use Coda\Cms\Models\Contracts\HasForms;

abstract class AbstractMetric extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public static function getName(): string
    {
        $name = str_replace('Metric', '', class_basename(static::class));

        return preg_replace('/([a-z])([A-Z])/', '$1 $2', $name);
    }

    /** @return array<\Coda\Cms\Form\Fields\Field> */
    abstract public static function fields(): array;

    /** @return array<string, string> */
    abstract public static function derivedValues(array $fieldValues): array;

    public static function getForm(): Form|array
    {
        return Form::make()
            ->fieldset(static::getName(), static::fields());
    }
}
