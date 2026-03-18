<?php

namespace App\Data\Training\Notes;

use Coda\Cms\Data\AbstractData;
use Coda\Cms\Form\Concerns\InteractsWithForms;
use Coda\Cms\Models\Contracts\HasForms;

abstract class AbstractNoteType extends AbstractData implements HasForms
{
    use InteractsWithForms;

    abstract public static function defaultColor(): string;

    abstract public static function label(): string;

    public static function fields(): array
    {
        return [];
    }
}
