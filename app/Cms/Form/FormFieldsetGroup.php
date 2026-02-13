<?php

namespace App\Cms\Form;

class FormFieldsetGroup
{
    public array $fieldsets = [];

    public ?string $label = null;

    public array $headerFields = [];

    public ?string $headerPrefix = null;

    public static function make(array $fieldsets, ?string $label = null, array $headerFields = [], ?string $headerPrefix = null): static
    {
        $group = new static;
        $group->fieldsets = array_values($fieldsets);
        $group->label = $label;
        $group->headerFields = $headerFields;
        $group->headerPrefix = $headerPrefix;

        return $group;
    }
}
