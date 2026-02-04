<?php

namespace App\Data;

use App\Models\Contracts\HasForms;
use Illuminate\Database\Eloquent\Model;

abstract class AbstractConfig extends AbstractData implements HasForms
{
    abstract public static function accessor(): string;

    public static function fromConfig(Model $exercise): static
    {
        $data = $exercise->config->get(static::accessor(), []);

        return static::from($data);
    }

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
