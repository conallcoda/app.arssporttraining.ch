<?php

namespace App\Data\Training\Blocks;

use Coda\Cms\Data\AbstractData;
use Coda\FormKit\Concerns\InteractsWithForms;
use Coda\FormKit\Contracts\HasForms;

abstract class AbstractBlockType extends AbstractData implements HasForms
{
    use InteractsWithForms;

    abstract public static function defaultColor(): string;

    abstract public static function label(): string;

    public static function fields(array $context = []): array
    {
        return [];
    }
}
