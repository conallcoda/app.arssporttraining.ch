<?php

namespace App\Filament\Extensions\List;

use App\Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ConfigurableTable
{
    public static function buildFromConfig(Table $table, array $config): Table
    {
        $table = static::applyDefaults($table, $config);

        if (isset($config['columns'])) {
            $columns = $config['columns'];
            $modelClass = $config['model'] ?? null;
            $columns = static::addTimestampColumns($columns, $modelClass);
            $table->columns(static::buildComponents($columns));
        }

        if (isset($config['filters'])) {
            $table->filters(static::buildComponents($config['filters']));
        }

        if (isset($config['default_sort'])) {
            $sortConfig = $config['default_sort'];
            if (is_string($sortConfig)) {
                $table->defaultSort($sortConfig);
            } elseif (is_array($sortConfig)) {
                $table->defaultSort($sortConfig['column'], $sortConfig['direction'] ?? 'asc');
            }
        }

        return $table;
    }

    protected static function addTimestampColumns(array $columns, ?string $modelClass = null): array
    {
        $timestampColumns = [
            'created_at' => [
                'type' => TextColumn::class,
                'date_time' => true,
                'sortable' => true,
                'toggleable' => ['isToggledHiddenByDefault' => true],
            ],
            'updated_at' => [
                'type' => TextColumn::class,
                'date_time' => true,
                'sortable' => true,
                'toggleable' => ['isToggledHiddenByDefault' => true],
            ],
        ];

        if ($modelClass && in_array(SoftDeletes::class, class_uses_recursive($modelClass))) {
            $timestampColumns['deleted_at'] = [
                'type' => TextColumn::class,
                'date_time' => true,
                'sortable' => true,
                'toggleable' => ['isToggledHiddenByDefault' => true],
            ];
        }

        foreach ($timestampColumns as $columnName => $columnConfig) {
            if (!isset($columns[$columnName])) {
                $columns[$columnName] = $columnConfig;
            }
        }

        return $columns;
    }

    protected static function applyDefaults(Table $table, array $config): Table
    {
        $recordActions = $config['record_actions'] ?? [
            EditAction::make(),
            DeleteAction::make(),
        ];

        $toolbarActions = $config['toolbar_actions'] ?? [
            BulkActionGroup::make([
                DeleteBulkAction::make(),
                ForceDeleteBulkAction::make(),
                RestoreBulkAction::make(),
            ]),
        ];

        $paginationPageOption = $config['default_pagination_page_option'] ?? 50;

        return $table
            ->recordActions($recordActions)
            ->toolbarActions($toolbarActions)
            ->defaultPaginationPageOption($paginationPageOption);
    }

    protected static function buildComponents(array $componentsConfig): array
    {
        return collect($componentsConfig)->map(function (array $componentConfig, string $componentName) {
            $type = $componentConfig['type'];
            $component = $type::make($componentConfig['name'] ?? $componentName);

            foreach ($componentConfig as $key => $value) {
                if (in_array($key, ['type', 'name'])) {
                    continue;
                }

                $method = Str::camel($key);

                if ($key === 'relationship') {
                    $component->relationship($value['name'], $value['title_attribute']);
                } elseif ($key === 'suffix_badges') {
                    $component->suffixBadges($value);
                } elseif ($key === 'toggleable' && is_array($value)) {
                    $component->toggleable(true, $value['isToggledHiddenByDefault'] ?? false);
                } elseif (method_exists($component, $method)) {
                    if (is_bool($value)) {
                        if ($value === true) {
                            $component->$method();
                        }
                    } elseif (is_array($value) && array_is_list($value)) {
                        $component->$method(...$value);
                    } else {
                        $component->$method($value);
                    }
                }
            }

            return $component;
        })->toArray();
    }
}
