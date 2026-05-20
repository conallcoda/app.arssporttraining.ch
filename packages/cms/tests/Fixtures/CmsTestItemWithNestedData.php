<?php

namespace Coda\Cms\Tests\Fixtures;

use Coda\Cms\Data\AbstractData;
use Illuminate\Database\Eloquent\Model;

class CmsTestItemWithNestedData extends AbstractData
{
    public function __construct(
        public ?int $id = null,
        public string $name = '',
        public ?CmsTestNestedProfileData $profile = null,
    ) {}

    public static function fromModel(Model $model): self
    {
        return new self(
            id: $model->id,
            name: $model->name ?? '',
            profile: CmsTestNestedProfileData::fromModel($model),
        );
    }
}
