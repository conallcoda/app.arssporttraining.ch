<?php

namespace App\Cms\Form;

use App\Cms\Form\Concerns\HasCondition;
use App\Cms\Form\Concerns\HasDefault;
use App\Cms\Form\Concerns\HasLabel;
use App\Cms\Form\Concerns\HasValidation;

abstract class Field
{
    use HasCondition;
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
            } elseif ($field->validationRules || $field->required) {
                $fieldRules = $field->validationRules ?? '';
                if ($field->required && ! str_contains($fieldRules, 'required')) {
                    $fieldRules = $fieldRules ? "required|{$fieldRules}" : 'required';
                }
                $rules["{$prefix}{$field->name}"] = $fieldRules;
            }
        }

        return $rules;
    }
}
