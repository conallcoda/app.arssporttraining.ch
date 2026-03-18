<?php

namespace App\Data\Training\Blocks;

use Coda\Cms\Form\Fields\Number;

class CategoryBlockType extends AbstractBlockType
{
    public static function defaultColor(): string
    {
        return '';
    }

    public static function label(): string
    {
        return __('Category');
    }

    public static function fields(array $context = []): array
    {
        $categorySlug = $context['categorySlug'] ?? null;

        if ($categorySlug === 'strength') {
            return [
                Number::make('config.goal')
                    ->label(__('Goal'))
                    ->default(10)
                    ->min(0)
                    ->max(100)
                    ->suffix('%'),
            ];
        }

        return [];
    }
}
