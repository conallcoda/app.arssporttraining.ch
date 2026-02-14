<?php

namespace Coda\Cms;

abstract class Module
{
    abstract public function name(): string;

    /** @return PageDefinition[] */
    abstract public function pages(): array;

    /** @return ComponentDefinition[] */
    public function components(): array
    {
        return [];
    }
}
