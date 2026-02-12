<?php

namespace App\Cms\Form;

class FormFieldsetGroup
{
    public array $fieldsets = [];

    public ?string $label = null;

    public static function make(array $fieldsets, ?string $label = null): static
    {
        $group = new static;
        $group->fieldsets = array_values($fieldsets);
        $group->label = $label;

        return $group;
    }
}
