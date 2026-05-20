<?php

namespace Coda\Cms\Tests\Fixtures;

use Coda\Cms\Data\AbstractData;
use Illuminate\Database\Eloquent\Model;

class CmsTestNestedProfileData extends AbstractData
{
    public function __construct(
        public ?string $subtitle = null,
    ) {}

    public static function fromModel(Model $model): self
    {
        return new self(
            subtitle: $model->status,
        );
    }
}
