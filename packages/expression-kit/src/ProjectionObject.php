<?php

namespace Coda\ExpressionKit;

final class ProjectionObject
{
    /**
     * @var array<int, ProjectionField>
     */
    private array $fields = [];

    private function __construct(
        public readonly string $key,
        public readonly ?string $label = null,
    ) {}

    public static function make(string $key): static
    {
        return new static($key);
    }

    public function label(string $label): static
    {
        $clone = new static($this->key, $label);
        $clone->fields = $this->fields;

        return $clone;
    }

    public function field(string|ProjectionField $field): static
    {
        $clone = new static($this->key, $this->label);
        $clone->fields = $this->fields;
        $clone->fields[] = is_string($field) ? ProjectionField::make($field) : $field;

        return $clone;
    }

    /**
     * @return array<int, ProjectionField>
     */
    public function fields(): array
    {
        return $this->fields;
    }
}
