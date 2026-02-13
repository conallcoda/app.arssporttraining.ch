<?php

namespace App\Cms\Form;

use Illuminate\Support\Str;

class FormFieldsetGroup
{
    public string $name = '';

    public array $fieldsets = [];

    public ?string $label = null;

    public array $headerFields = [];

    public ?string $headerPrefix = null;

    public bool $scrollableTabs = true;

    public ?string $class = null;

    public static function make(array $fieldsets, ?string $label = null, array $headerFields = [], ?string $headerPrefix = null): static
    {
        $group = new static;
        $group->name = $label ? Str::snake($label) : '';
        $group->fieldsets = array_values($fieldsets);
        $group->label = $label;
        $group->headerFields = $headerFields;
        $group->headerPrefix = $headerPrefix;

        return $group;
    }

    public function scrollableTabs(bool $scrollable = true): static
    {
        $this->scrollableTabs = $scrollable;

        return $this;
    }

    public function class(string $class): static
    {
        $this->class = $class;

        return $this;
    }
}
