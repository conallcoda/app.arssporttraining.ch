<?php

namespace App\Cms\Modules;

use App\Livewire\Training\CategoryList;
use Coda\Cms\ComponentDefinition;
use Coda\Cms\ComponentType;
use Coda\Cms\Module;
use Coda\Cms\PageDefinition;

class CategoryListModule extends Module
{
    public function name(): string
    {
        return 'category-list';
    }

    public function pages(): array
    {
        return [
            PageDefinition::make('category-list-index')
                ->route('/training/categories')
                ->title('ARS - Athlete Training // Categories')
                ->heading('Categories')
                ->content(['training.category-list']),
        ];
    }

    public function components(): array
    {
        return [
            ComponentDefinition::make('category-list', CategoryList::class)
                ->type(ComponentType::List),
        ];
    }
}
