<?php

namespace App\Data\Form;

class FluxField
{
    public function __construct(
        public string $name,
        public string $type,
        public ?string $label = null,
        public bool $required = false,
        public array $options = [],
        public bool $searchable = false,
        public ?string $placeholder = null,
        public ?float $min = null,
        public ?float $max = null,
        public ?float $step = null,
        public ?string $suffix = null,
        public array $schema = [],
        public bool $disabled = false,
        public array $computedFrom = [],
        public ?string $validationRules = null,
        public bool $unique = false,
        public bool $live = false,
        public ?string $helpText = null,
    ) {}

    public static function select(string $name): static
    {
        return new static($name, 'select');
    }

    public static function text(string $name): static
    {
        return new static($name, 'text');
    }

    public static function number(string $name): static
    {
        return new static($name, 'number');
    }

    public static function repeater(string $name): static
    {
        return new static($name, 'repeater');
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function required(bool $required = true): static
    {
        $this->required = $required;

        return $this;
    }

    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function searchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;

        return $this;
    }

    public function placeholder(string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function min(float $min): static
    {
        $this->min = $min;

        return $this;
    }

    public function max(float $max): static
    {
        $this->max = $max;

        return $this;
    }

    public function step(float $step): static
    {
        $this->step = $step;

        return $this;
    }

    public function suffix(string $suffix): static
    {
        $this->suffix = $suffix;

        return $this;
    }

    public function schema(array $schema): static
    {
        $this->schema = $schema;

        return $this;
    }

    public function disabled(bool $disabled = true): static
    {
        $this->disabled = $disabled;

        return $this;
    }

    public function computedFrom(array $fields): static
    {
        $this->computedFrom = $fields;

        return $this;
    }

    public function rules(string $rules): static
    {
        $this->validationRules = $rules;

        return $this;
    }

    public function unique(bool $unique = true): static
    {
        $this->unique = $unique;

        return $this;
    }

    public function live(bool $live = true): static
    {
        $this->live = $live;

        return $this;
    }

    public function helpText(string $helpText): static
    {
        $this->helpText = $helpText;

        return $this;
    }

    public static function buildValidationRules(array $fields, string $prefix = ''): array
    {
        $rules = [];

        foreach ($fields as $field) {
            if ($field->type === 'repeater') {
                $childRules = self::buildValidationRules($field->schema, "{$prefix}{$field->name}.*.");
                $rules = array_merge($rules, $childRules);
            } elseif ($field->validationRules) {
                $rules["{$prefix}{$field->name}"] = $field->validationRules;
            }
        }

        return $rules;
    }
}
