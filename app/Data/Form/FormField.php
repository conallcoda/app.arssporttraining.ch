<?php

namespace App\Data\Form;

use Spatie\LaravelOptions\Options;

class FormField
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
        public mixed $default = null,
        public bool $sortable = false,
        public string $displayAttribute = 'name',
        public string $valueAttribute = 'id',
        public ?string $enumClass = null,
        public bool $multiple = false,
        public bool $clearable = false,
        public ?string $variant = null,
        public ?string $size = null,
        public ?string $mask = null,
        public array $ticks = [],
    ) {}

    public static function select(string $name): static
    {
        return new static($name, 'select');
    }

    public static function text(string $name): static
    {
        return new static($name, 'text');
    }

    public static function tut(string $name, bool $simple = false): static
    {
        $field = new static($name, 'text');

        if ($simple) {
            return $field;
        }

        return $field
            ->mask('99-99-99-99')
            ->placeholder('3010')
            ->rules('nullable|regex:/^\d{2}-\d{2}-\d{2}-\d{2}$/');
    }

    public static function number(string $name): static
    {
        return new static($name, 'number');
    }

    public static function percentage(string $name): static
    {
        return (new static($name, 'number'))
            ->min(0)
            ->max(999)
            ->step(1)
            ->suffix('%');
    }

    public static function repeater(string $name): static
    {
        return new static($name, 'repeater');
    }

    public static function radioSegmented(string $name): static
    {
        return new static($name, 'radioSegmented');
    }

    public static function date(string $name): static
    {
        return new static($name, 'date');
    }

    public static function relationship(string $name): static
    {
        return new static($name, 'relationship');
    }

    public static function pillbox(string $name): static
    {
        return new static($name, 'pillbox');
    }

    public static function slider(string $name): static
    {
        return (new static($name, 'slider'))->live();
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

    public function enum(string $enumClass): static
    {
        $this->enumClass = $enumClass;

        $this->options = collect(Options::forEnum($enumClass)->toArray())
            ->mapWithKeys(fn (array $option) => [$option['value'] => $option['label']])
            ->toArray();

        $cases = $enumClass::cases();
        $values = array_map(fn ($case) => $case->value, $cases);

        $this->validationRules = 'required|string|in:'.implode(',', $values);
        $this->default = $cases[0]->value ?? null;

        return $this;
    }

    public function searchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;

        return $this;
    }

    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    public function clearable(bool $clearable = true): static
    {
        $this->clearable = $clearable;

        return $this;
    }

    public function variant(string $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    public function size(string $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function placeholder(string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function mask(string $mask): static
    {
        $this->mask = $mask;

        return $this;
    }

    public function ticks(array $ticks): static
    {
        $this->ticks = $ticks;

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

    public function default(mixed $default): static
    {
        $this->default = $default;

        return $this;
    }

    public function sortable(bool $sortable = true): static
    {
        $this->sortable = $sortable;

        return $this;
    }

    public function displayAttribute(string $displayAttribute): static
    {
        $this->displayAttribute = $displayAttribute;

        return $this;
    }

    public function valueAttribute(string $valueAttribute): static
    {
        $this->valueAttribute = $valueAttribute;

        return $this;
    }

    public static function buildDefaults(array $fields): array
    {
        $defaults = [];

        foreach ($fields as $field) {
            if ($field->default !== null) {
                $defaults[$field->name] = $field->default;
            }
        }

        return $defaults;
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
