<?php

namespace App\Data\Form;

class FluxFieldset
{
    public function __construct(
        public string $label,
        public array $fields = [],
    ) {}

    public static function make(string $label): static
    {
        return new static($label);
    }

    public function schema(array $fields): static
    {
        $this->fields = $fields;

        return $this;
    }

    public static function buildValidationRules(array $items, string $prefix = ''): array
    {
        $rules = [];

        foreach ($items as $item) {
            if ($item instanceof FluxFieldset) {
                $itemRules = FluxField::buildValidationRules($item->fields, $prefix);
            } else {
                $itemRules = FluxField::buildValidationRules([$item], $prefix);
            }
            $rules = array_merge($rules, $itemRules);
        }

        return $rules;
    }
}
