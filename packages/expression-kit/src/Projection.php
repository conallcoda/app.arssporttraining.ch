<?php

namespace Coda\ExpressionKit;

final class Projection
{
    /**
     * @var array<int, ProjectionField|ProjectionObject>
     */
    private array $members = [];

    public static function make(): static
    {
        return new static;
    }

    public function field(string|ProjectionField $field): static
    {
        $this->members[] = is_string($field) ? ProjectionField::make($field) : $field;

        return $this;
    }

    public function object(string|ProjectionObject $object, ?callable $configure = null): static
    {
        $resolved = is_string($object) ? ProjectionObject::make($object) : $object;

        if ($configure !== null) {
            $configured = $configure($resolved);

            if ($configured instanceof ProjectionObject) {
                $resolved = $configured;
            }
        }

        $this->members[] = $resolved;

        return $this;
    }

    /**
     * @return array<int, ProjectionField|ProjectionObject>
     */
    public function members(): array
    {
        return $this->members;
    }
}
