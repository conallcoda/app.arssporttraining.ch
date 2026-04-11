<?php

namespace App\Data\Athlete\Metric;

use Coda\Cms\Data\AbstractData;
use Coda\FormKit\Concerns\InteractsWithForms;
use Coda\FormKit\Contracts\HasForms;
use Coda\FormKit\Fields\Field;
use Coda\FormKit\Form;

abstract class AbstractMetric extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public static function getName(): string
    {
        $name = str_replace('Metric', '', class_basename(static::class));

        return preg_replace('/([a-z])([A-Z])/', '$1 $2', $name);
    }

    /** @return array<Field> */
    abstract public static function fields(): array;

    /** @return array<string, string> */
    abstract public static function derivedValues(array $fieldValues): array;

    abstract public function summary(): string;

    /** @return array{label: string} */
    abstract public function badge(string $prefix): array;

    public static function getForm(): Form|array
    {
        return Form::make()
            ->fieldset(static::getName(), static::fields());
    }
}
