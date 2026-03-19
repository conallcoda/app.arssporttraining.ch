<?php

namespace App\Data\Training\Blocks;

use Coda\Cms\Form\Fields\Number;
use Coda\Cms\Form\Fields\SwitchField;

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
                    ->required()
                    ->default(10)
                    ->min(0)
                    ->max(100)
                    ->suffix('%'),
                SwitchField::make('config.autoRecord1rm')
                    ->label(__('Automatically record 1RM?'))
                    ->helpText(__('At the end of the block the target 1RM will be recorded so it can be applied to future blocks.'))
                    ->default(false),
            ];
        }

        return [];
    }
}
