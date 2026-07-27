<?php

namespace Coda\SchemaKit\Attributes;

use Attribute;
use Coda\SchemaKit\DateTimeCastTransformer;
use Coda\SchemaKit\DateTimeInput;
use Coda\SchemaKit\FieldDefinition;
use Spatie\LaravelData\Attributes\WithCastAndTransformer;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class DateTime extends WithCastAndTransformer implements AppliesToFieldDefinition, ProvidesValidationRules
{
    public function __construct(
        ?string $label = null,
        ?string $attribute = null,
        ?string $help = null,
        ?bool $title = null,
        ?bool $modal = null,
        ?bool $readable = null,
        ?bool $writable = null,
        ?bool $formVisible = null,
        private readonly string|array|null $rules = null,
        private readonly ?array $inputFormats = null,
        private readonly ?string $outputFormat = null,
        private readonly ?string $type = null,
        private readonly ?string $setTimeZone = null,
        private readonly ?string $timeZone = null,
    ) {
        parent::__construct(
            DateTimeCastTransformer::class,
            inputFormats: $this->inputFormats,
            outputFormat: $this->outputFormat,
            type: $this->type,
            setTimeZone: $this->setTimeZone,
            timeZone: $this->timeZone,
        );

        $this->label = $label;
        $this->attribute = $attribute;
        $this->help = $help;
        $this->title = $title;
        $this->modal = $modal;
        $this->readable = $readable;
        $this->writable = $writable;
        $this->formVisible = $formVisible;
    }

    private ?string $label = null;
    private ?string $attribute = null;
    private ?string $help = null;
    private ?bool $title = null;
    private ?bool $modal = null;
    private ?bool $readable = null;
    private ?bool $writable = null;
    private ?bool $formVisible = null;

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

        $field->input(DateTimeInput::make());
    }

    public function rules(): array
    {
        $outputFormat = $this->outputFormat ?? 'Y-m-d\TH:i';

        return [
            'date',
            "date_format:{$outputFormat}",
            ...((is_array($this->rules) ? $this->rules : ($this->rules !== null ? [$this->rules] : []))),
        ];
    }
}
