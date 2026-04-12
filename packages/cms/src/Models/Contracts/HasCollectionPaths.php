<?php

namespace Coda\Cms\Models\Contracts;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

interface HasCollectionPaths
{
    public function getCollectionBasePath(string $collectionName, ?Media $media = null): ?string;
}
