<?php

namespace App\Data\Form;

use App\Data\Form\Concerns\HasDefault;
use App\Data\Form\Concerns\HasLabel;
use App\Data\Form\Concerns\HasValidation;

abstract class Field
{
    use HasDefault;
    use HasLabel;
    use HasValidation;

    public string $name;

    public string $type;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    public static function buildDefaults(array $fields): array
    {
        $defaults = [];

        foreach ($fields as $field) {
            if ($field->default !== null) {
                $defaults[$field->name] = $field->default;
            }
        }

        return $defaults;
    }

    public static function buildValidationRules(array $fields, string $prefix = ''): array
    {
        $rules = [];

        foreach ($fields as $field) {
            if ($field->type === 'repeater') {
                $childRules = self::buildValidationRules($field->schema, "{$prefix}{$field->name}.*.");
                $rules = array_merge($rules, $childRules);
            } elseif ($field->validationRules) {
                $rules["{$prefix}{$field->name}"] = $field->validationRules;
            }
        }

        return $rules;
    }
}
