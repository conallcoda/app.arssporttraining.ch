<?php

namespace Coda\SchemaKit;

use Closure;

class ComputedDefinition extends FieldDefinition
{
    public static function make(string $name): static
    {
        return (new static($name))->writable(false);
    }

    public function computedUsing(Closure|string|null $readUsing): static
    {
        return $this->readUsing($readUsing)->writable(false);
    }

    public function definitionType(): string
    {
        return 'computed';
    }
}
