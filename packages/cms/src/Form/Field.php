<?php

namespace Coda\Cms\Form;

use Coda\Cms\Form\Concerns\HasCondition;
use Coda\Cms\Form\Concerns\HasDefault;
use Coda\Cms\Form\Concerns\HasLabel;
use Coda\Cms\Form\Concerns\HasValidation;

abstract class Field
{
    use HasCondition;
    use HasDefault;
    use HasLabel;
    use HasValidation;

    private static array $optionCache = [];

    public string $name;

    public string $type;

    public static function getCachedOptions(string $key): mixed
    {
        return self::$optionCache[$key] ?? null;
    }

    public static function setCachedOptions(string $key, mixed $value): void
    {
        self::$optionCache[$key] = $value;
    }

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
            if ($field->type === 'file-upload') {
                continue;
            }

            if (method_exists($field, 'resolveDefault')) {
                $resolved = $field->resolveDefault($defaults);

                if ($resolved !== null) {
                    $defaults[$field->name] = $resolved;
                }
            } elseif ($field->default !== null) {
                $defaults[$field->name] = $field->default;
            }
        }

        return $defaults;
    }

    public static function buildValidationRules(array $fields, string $prefix = ''): array
    {
        $rules = [];

        foreach ($fields as $field) {
            if ($field->type === 'file-upload') {
                continue;
            }

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
