<?php

namespace Coda\Cms\Display;

use Coda\Cms\Display\DisplayField;
use Coda\Cms\Display\DisplayFields\Image as ImageField;

class CardDefinition
{
    public ?CardField $titleField = null;

    public ?CardField $subtitleField = null;

    public ?CardField $imageField = null;

    /** @var CardField[] */
    public array $metaFields = [];

    /** @var CardField[] */
    public array $bodyFields = [];

    /** @var CardField[] */
    public array $badgeFields = [];

    public ?string $infoView = null;

    public ?string $view = null;

    public static function make(): static
    {
        return new static;
    }

    public function title(string|CardField $field): static
    {
        $this->titleField = $this->normalizeField($field);

        return $this;
    }

    public function subtitle(string|CardField $field): static
    {
        $this->subtitleField = $this->normalizeField($field);

        return $this;
    }

    public function image(string|CardField $field): static
    {
        $this->imageField = $this->normalizeField($field);

        return $this;
    }

    /** @param CardField[]|string[] $fields */
    public function meta(array $fields): static
    {
        $this->metaFields = array_map(fn (mixed $field) => $this->normalizeField($field), $fields);

        return $this;
    }

    /** @param CardField[]|string[] $fields */
    public function body(array $fields): static
    {
        $this->bodyFields = array_map(fn (mixed $field) => $this->normalizeField($field), $fields);

        return $this;
    }

    /** @param CardField[]|string[] $fields */
    public function badges(array $fields): static
    {
        $this->badgeFields = array_map(
            fn (mixed $field) => $this->normalizeField($field)->badge(),
            $fields
        );

        return $this;
    }

    public function view(string $view): static
    {
        $this->view = $view;

        return $this;
    }

    public function infoView(string $view): static
    {
        $this->infoView = $view;

        return $this;
    }

    /** @param DisplayField[] $fields */
    public static function fromDisplayFields(array $fields, ?string $titleFieldName = null, ?string $view = null): static
    {
        $definition = static::make();
        $imageField = collect($fields)->first(fn (mixed $field) => $field instanceof ImageField);
        $nonImageFields = collect($fields)->reject(fn (mixed $field) => $field instanceof ImageField)->values();
        $titleField = $titleFieldName
            ? $nonImageFields->first(fn (DisplayField $field) => $field->field === $titleFieldName)
            : $nonImageFields->first();

        if ($imageField instanceof ImageField) {
            $definition->image(CardField::make($imageField->field)->label($imageField->getDisplayLabel()));
        }

        if ($titleField instanceof DisplayField) {
            $definition->title(CardField::make($titleField->field)->label($titleField->getDisplayLabel()));
        }

        $remainingFields = $nonImageFields
            ->reject(fn (DisplayField $field) => $titleField && $field->field === $titleField->field)
            ->values()
            ->all();

        if ($remainingFields !== []) {
            $definition->body(array_map(
                fn (DisplayField $field) => CardField::make($field->field)->label($field->getDisplayLabel()),
                $remainingFields
            ));
        }

        if ($view !== null && $view !== '') {
            $definition->view($view);
        }

        return $definition;
    }

    protected function normalizeField(string|CardField $field): CardField
    {
        return $field instanceof CardField ? $field : CardField::make($field);
    }
}
