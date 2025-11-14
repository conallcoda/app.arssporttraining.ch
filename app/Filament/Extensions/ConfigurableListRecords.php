<?php

namespace App\Filament\Extensions;

use App\Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ConfigurableListRecords extends ListRecords
{
    protected static string $resource = '';

    private static array $resourceMap = [];

    protected static array $config = [];

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        if (!isset(static::$config['tabs'])) {
            return parent::getTabs();
        }

        $tabs = [];
        foreach (static::$config['tabs'] as $key => $tabConfig) {
            $tab = Tab::make($tabConfig['label'] ?? ucfirst($key));

            if (isset($tabConfig['query'])) {
                $tab->modifyQueryUsing(function (Builder $query) use ($tabConfig) {
                    foreach ($tabConfig['query'] as $field => $value) {
                        $query->where($field, $value);
                    }
                });
            }

            if (isset($tabConfig['badge'])) {
                $tab->badge(function () use ($tabConfig, $key) {
                    $cacheKey = 'tab_badge_' . static::getResource() . '_' . $key;

                    return cache()->remember($cacheKey, 60, function () use ($tabConfig) {
                        $model = $tabConfig['badge']['model'] ?? static::getResource()::getModel();
                        $query = $model::query();

                        if (isset($tabConfig['badge']['query'])) {
                            foreach ($tabConfig['badge']['query'] as $field => $value) {
                                $query->where($field, $value);
                            }
                        }

                        return $query->count();
                    });
                });
            }

            $tabs[$key] = $tab;
        }

        return $tabs;
    }

    public static function configure(array $options): string
    {
        $resource = $options['resource'];
        $config = $options['tabs'] ?? [];

        $cacheKey = $resource . '::' . md5(json_encode($config));

        if (!isset(self::$resourceMap[$cacheKey])) {
            $baseClassHash = filemtime(__FILE__);
            $configHash = md5(json_encode($config));
            $resourceHash = md5($resource);
            $className = 'ConfigurableListRecords_' . $resourceHash . '_' . $configHash . '_' . $baseClassHash;
            $fullClassName = __NAMESPACE__ . '\\' . $className;

            if (!class_exists($fullClassName, false)) {
                $wrappedConfig = ['tabs' => $config];
                $code = sprintf(
                    'namespace %s; class %s extends ConfigurableListRecords { protected static string $resource = %s; protected static array $config = %s; }',
                    __NAMESPACE__,
                    $className,
                    var_export($resource, true),
                    var_export($wrappedConfig, true)
                );

                if (function_exists('eval')) {
                    eval($code);
                } else {
                    $filePath = storage_path('framework/cache/filament-pages/' . $className . '.php');

                    if (!file_exists($filePath)) {
                        $dir = dirname($filePath);
                        if (!is_dir($dir)) {
                            mkdir($dir, 0755, true);
                        }
                        file_put_contents($filePath, '<?php ' . $code);
                    }

                    require_once $filePath;
                }
            }

            self::$resourceMap[$cacheKey] = $fullClassName;
        }

        return self::$resourceMap[$cacheKey];
    }
}
