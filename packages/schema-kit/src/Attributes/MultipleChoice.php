<?php

namespace Coda\SchemaKit\Attributes;

use Attribute;
use Coda\SchemaKit\FieldDefinition;
use Coda\SchemaKit\SelectInput;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class MultipleChoice extends Field
{
    /**
     * @param  array<int|string, mixed>|null  $options
     */
    public function __construct(
        ?string $label = null,
        ?string $attribute = null,
        ?string $help = null,
        ?bool $title = null,
        ?bool $modal = null,
        ?bool $readable = null,
        ?bool $writable = null,
        ?bool $formVisible = null,
        string|array|null $rules = null,
        private readonly ?array $options = null,
        private readonly bool $searchable = false,
        private readonly bool $clearable = false,
    ) {
        parent::__construct($label, $attribute, $help, $title, $modal, $readable, $writable, $formVisible, $rules);
    }

    public function apply(FieldDefinition $field): void
    {
        parent::apply($field);

        $input = SelectInput::make()
            ->multiple()
            ->searchable($this->searchable)
            ->clearable($this->clearable);

        if ($this->options !== null) {
            $input->options($this->options);
        }

        $field->input($input);
    }
}
