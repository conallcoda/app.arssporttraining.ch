<?php

namespace App\Cms\Data;

abstract class AbstractConfig extends AbstractData
{
    abstract public static function getFields(): array;

    public function toBadges(): array
    {
        $badges = [];

        foreach (static::getFields() as $field) {
            $value = $this->{$field->name} ?? null;

            if ($value !== null && $value !== '') {
                $displayValue = (string) $value;

                if ($field->suffix) {
                    $displayValue .= ' '.$field->suffix;
                }

                $badges[] = [
                    'label' => $displayValue,
                    'modalField' => $field->name,
                ];
            }
        }

        return $badges;
    }
}
