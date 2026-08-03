<?php

namespace Coda\Cms\Data;

final class RelationRefData extends RefData
{
    public function __construct(
        ?string $type = null,
        int|string|null $id = null,
        ?string $label = null,
        public ?string $slug = null,
        public ?string $href = null,
        array $meta = [],
    ) {
        parent::__construct($type, $id, $label, $meta);
    }
}
