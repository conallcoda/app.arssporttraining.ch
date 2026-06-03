<?php

namespace Coda\SchemaKit\Attributes;

use Attribute;
use Coda\SchemaKit\FieldDefinition;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
class Field implements AppliesToFieldDefinition, ProvidesValidationRules
{
    public function __construct(
        private readonly ?string $label = null,
        private readonly ?string $attribute = null,
        private readonly ?string $help = null,
        private readonly ?bool $title = null,
        private readonly ?bool $modal = null,
        private readonly ?bool $readable = null,
        private readonly ?bool $writable = null,
        private readonly ?bool $formVisible = null,
        private readonly string|array|null $rules = null,
    ) {}

    public function apply(FieldDefinition $field): void
    {
        if ($this->label !== null) {
            $field->label($this->label);
        }

        if ($this->attribute !== null) {
            $field->attribute($this->attribute);
        }

        if ($this->help !== null) {
            $field->help($this->help);
        }

        if ($this->title !== null) {
            $field->title($this->title);
        }

        if ($this->modal !== null) {
            $field->modal($this->modal);
        }

        if ($this->readable !== null) {
            $field->readable($this->readable);
        }

        if ($this->writable !== null) {
            $field->writable($this->writable);
        }

        if ($this->formVisible !== null) {
            $field->formVisible($this->formVisible);
        }
    }

    public function rules(): array
    {
        if ($this->rules === null) {
            return [];
        }

        return is_array($this->rules) ? $this->rules : [$this->rules];
    }
}
