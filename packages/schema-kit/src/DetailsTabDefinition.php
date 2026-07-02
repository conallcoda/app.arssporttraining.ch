<?php

namespace Coda\SchemaKit;

final class DetailsTabDefinition
{
    private array $schema = [];

    private array $left = [];

    private array $right = [];

    private mixed $infoBox = null;

    public function __construct(
        private readonly string $title,
    ) {}

    public static function make(string $title): static
    {
        return new static($title);
    }

    public function schema(array $schema): static
    {
        $this->schema = $schema;

        return $this;
    }

    public function left(array $left): static
    {
        $this->left = array_values($left);

        return $this;
    }

    public function right(array $right): static
    {
        $this->right = array_values($right);

        return $this;
    }

    public function infoBox(mixed $infoBox): static
    {
        $this->infoBox = $infoBox;

        return $this;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function getSchema(): array
    {
        return $this->schema;
    }

    public function getLeft(): array
    {
        return $this->left;
    }

    public function getRight(): array
    {
        return $this->right;
    }

    public function getInfoBox(): mixed
    {
        return $this->infoBox;
    }
}
