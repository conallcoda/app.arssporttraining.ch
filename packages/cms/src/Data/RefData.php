<?php

namespace Coda\Cms\Data;

abstract class RefData extends AbstractData
{
    public function __construct(
        public ?string $type = null,
        public int|string|null $id = null,
        public ?string $label = null,
        public array $meta = [],
    ) {}
}
