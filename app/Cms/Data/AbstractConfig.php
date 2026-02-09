<?php

namespace App\Cms\Data;

abstract class AbstractConfig extends AbstractData
{
    public static function getFields(array $data = []): array
    {
        return [];
    }

    public function toBadges(): array
    {
        $badges = [];

        foreach (static::getFields($this->toArray()) as $field) {
            $value = $this->{$field->name} ?? null;

            if ($value !== null && $value !== '') {
                $displayValue = (string) $value;

                if (isset($field->suffix) && $field->suffix) {
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
