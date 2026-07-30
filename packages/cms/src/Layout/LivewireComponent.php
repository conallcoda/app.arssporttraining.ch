<?php

namespace Coda\Cms\Layout;

class LivewireComponent
{
    /**
     * @param  array<string, mixed>  $props
     */
    public function __construct(
        public string $component,
        public array $props = [],
        public ?string $key = null,
    ) {}

    /**
     * @param  array<string, mixed>  $props
     */
    public static function make(string $component, array $props = [], ?string $key = null): static
    {
        return new static($component, $props, $key);
    }
}
