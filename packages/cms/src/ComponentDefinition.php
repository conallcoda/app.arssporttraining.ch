<?php

namespace Coda\Cms;

use Coda\Cms\Data\AbstractData;

class ComponentDefinition extends AbstractData
{
    public function __construct(
        public string $name = '',
        public string $component = '',
        public ComponentType $type = ComponentType::Custom,
        /** @var array<string, mixed> */
        public array $defaultOptions = [],
    ) {}

    public static function make(string $name, string $component): static
    {
        return new static(name: $name, component: $component);
    }

    public function type(ComponentType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function defaultOptions(array $defaultOptions): static
    {
        $this->defaultOptions = $defaultOptions;

        return $this;
    }
}
