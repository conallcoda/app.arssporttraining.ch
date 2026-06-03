<?php

namespace Coda\ExpressionKit;

final class ProjectionField
{
    public function __construct(
        public readonly string $key,
        public readonly string $source,
        public readonly ?string $label = null,
        public readonly ?string $type = null,
    ) {}

    public static function make(string $source, ?string $key = null): static
    {
        $segments = explode('.', $source);
        $resolvedKey = $key ?? end($segments) ?: $source;

        return new static($resolvedKey, $source);
    }

    public function label(string $label): static
    {
        return new static($this->key, $this->source, $label, $this->type);
    }

    public function type(string $type): static
    {
        return new static($this->key, $this->source, $this->label, $type);
    }
}
