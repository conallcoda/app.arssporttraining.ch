<?php

namespace App\Filament\Extensions;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class ConfigurableEditRecord extends EditRecord
{
    protected static string $resource = '';

    private static array $resourceMap = [];

    protected function getHeaderActions(): array
    {
        return [
            $this->getCancelFormAction()->formId('form'),
            $this->getSaveFormAction()->formId('form'),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            DeleteAction::make()
                ->extraAttributes(['class' => 'ml-auto']),
            ForceDeleteAction::make(),
            RestoreAction::make(),
            $this->getCancelFormAction()->formId('form'),
            $this->getSaveFormAction()->formId('form'),
        ];
    }

    public static function configure(array $options): string
    {
        $resource = $options['resource'];

        if (!isset(self::$resourceMap[$resource])) {
            $baseClassHash = filemtime(__FILE__);
            $resourceHash = md5($resource);
            $className = 'ConfigurableEditRecord_' . $resourceHash . '_' . $baseClassHash;
            $fullClassName = __NAMESPACE__ . '\\' . $className;

            if (!class_exists($fullClassName, false)) {
                $code = sprintf(
                    'namespace %s; class %s extends ConfigurableEditRecord { protected static string $resource = %s; }',
                    __NAMESPACE__,
                    $className,
                    var_export($resource, true)
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

            self::$resourceMap[$resource] = $fullClassName;
        }

        return self::$resourceMap[$resource];
    }
}
