<?php

namespace App\Filament\Extensions\List;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Parental\HasChildren;

class ChildTypeTabs
{
    public static function configure(string|array $options): array
    {
        if (is_string($options)) {
            $modelClass = $options;
            $includeAll = false;

            if (!in_array(HasChildren::class, class_uses_recursive($modelClass))) {
                throw new InvalidArgumentException("Model class {$modelClass} must use the HasChildren trait.");
            }
        } else {
            $modelClass = $options['model'];
            $includeAll = $options['all'] ?? false;
        }

        $childTypes = (new $modelClass)->getChildTypes();

        $tabs = [];

        if ($includeAll) {
            $tabs['all'] = [
                'label' => 'All',
                'badge' => ['model' => $modelClass],
            ];
        }

        foreach ($childTypes as $type => $childClass) {
            $tabs[$type] = [
                'label' => Str::title($type),
                'query' => ['type' => $type],
                'badge' => ['model' => $childClass],
            ];
        }

        return $tabs;
    }
}
