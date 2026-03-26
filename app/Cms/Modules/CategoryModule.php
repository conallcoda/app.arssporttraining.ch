<?php

namespace App\Cms\Modules;

use App\Livewire\Database\CategoryTree;
use Coda\Cms\ComponentDefinition;
use Coda\Cms\ComponentType;
use Coda\Cms\Module;
use Coda\Cms\PageDefinition;

class CategoryModule extends Module
{
    public function name(): string
    {
        return 'categories';
    }

    public function pages(): array
    {
        return [
            PageDefinition::make('category-index')
                ->route('/exercises/categories')
                ->heading('Exercise Categories')
                ->content(['database.category-tree']),
        ];
    }

    public function components(): array
    {
        return [
            ComponentDefinition::make('category-tree', CategoryTree::class)
                ->type(ComponentType::Tree),
        ];
    }
}
