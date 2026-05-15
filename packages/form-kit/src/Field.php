<?php

namespace Coda\FormKit;

use Coda\FormKit\Concerns\HasCondition;
use Coda\FormKit\Concerns\HasDefault;
use Coda\FormKit\Concerns\HasLabel;
use Coda\FormKit\Concerns\HasValidation;

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

    public static function clearCachedOptions(): void
    {
        self::$optionCache = [];
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

    public static function buildValidationRules(array $fields, string $prefix = '', array $data = []): array
    {
        $rules = [];

        foreach ($fields as $field) {
            if ($field->type === 'file-upload') {
                continue;
            }

            if ($field->type === 'repeater' || $field->type === 'relationship-selector') {
                $childRules = self::buildValidationRules($field->schema, "{$prefix}{$field->name}.*.", $data);
                $rules = array_merge($rules, $childRules);
            } elseif ($field->validationRules || $field->required) {
                $resolvedRules = $field->resolveValidationRules($data);
                $fieldRules = $resolvedRules ?? '';

                if (is_array($fieldRules)) {
                    if ($field->required && ! in_array('required', $fieldRules)) {
                        array_unshift($fieldRules, 'required');
                    }
                    $rules["{$prefix}{$field->name}"] = $fieldRules;
                } else {
                    if ($field->required && ! str_contains($fieldRules, 'required')) {
                        $fieldRules = $fieldRules ? "required|{$fieldRules}" : 'required';
                    }
                    $rules["{$prefix}{$field->name}"] = $fieldRules;
                }
            }
        }

        return $rules;
    }

    public static function buildValidationAttributes(array $fields, string $prefix = ''): array
    {
        $attributes = [];

        foreach ($fields as $field) {
            $attributes["{$prefix}{$field->name}"] = strtolower($field->getLabel());

            if ($field->type === 'repeater' || $field->type === 'relationship-selector') {
                $childAttributes = self::buildValidationAttributes($field->schema, "{$prefix}{$field->name}.*.");
                $attributes = array_merge($attributes, $childAttributes);
            }
        }

        return $attributes;
    }
}
