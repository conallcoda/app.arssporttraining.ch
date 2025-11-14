<?php

namespace App\Filament\Extensions\Form;

use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ConfigurableForm
{
    public static function buildFromConfig(Schema $schema, array $config): Schema
    {
        $sections = collect($config['sections'])->map(function (array $sectionConfig, string $sectionName) {
            $section = Section::make($sectionName)
                ->schema(static::buildFields($sectionConfig['fields']));

            if (isset($sectionConfig['columns'])) {
                $section->columns($sectionConfig['columns']);
            }

            return $section;
        });

        return $schema->components($sections->toArray());
    }

    protected static function buildFields(array $fieldsConfig): array
    {
        return collect($fieldsConfig)->map(function (array $fieldConfig, string $fieldName) {
            $type = $fieldConfig['type'];
            $component = $type::make($fieldConfig['name'] ?? $fieldName);

            foreach ($fieldConfig as $key => $value) {
                if (in_array($key, ['type', 'name'])) {
                    continue;
                }

                $method = Str::camel($key);

                if ($key === 'relationship') {
                    $component->relationship($value['name'], $value['title_attribute']);
                } elseif ($key === 'create_option_form') {
                    $component->createOptionForm(static::buildFields($value));
                } elseif ($key === 'schema') {
                    $component->schema(static::buildFields($value));
                } elseif ($key === 'simple') {
                    $type = $value['type'];
                    $simpleComponent = $type::make($value['name'] ?? 'item');

                    foreach ($value as $simpleKey => $simpleValue) {
                        if (in_array($simpleKey, ['type', 'name'])) {
                            continue;
                        }

                        $simpleMethod = Str::camel($simpleKey);

                        if (method_exists($simpleComponent, $simpleMethod)) {
                            if (is_bool($simpleValue)) {
                                if ($simpleValue === true) {
                                    $simpleComponent->$simpleMethod();
                                }
                            } else {
                                $simpleComponent->$simpleMethod($simpleValue);
                            }
                        }
                    }

                    $component->simple($simpleComponent);
                } elseif (method_exists($component, $method)) {
                    if (is_bool($value)) {
                        if ($value === true) {
                            $component->$method();
                        }
                    } else {
                        $component->$method($value);
                    }
                }
            }

            return $component;
        })->toArray();
    }
}
