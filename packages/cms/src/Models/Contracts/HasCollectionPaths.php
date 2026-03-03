<?php

namespace Coda\Cms\Models\Contracts;

interface HasCollectionPaths
{
    public function getCollectionBasePath(string $collectionName): ?string;
}
