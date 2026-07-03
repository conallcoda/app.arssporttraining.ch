<?php

namespace Coda\Cms\Support\PathGenerator;

use Coda\Cms\Models\Contracts\HasCollectionPaths;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator;

class CollectionPathGenerator extends DefaultPathGenerator
{
    protected function getBasePath(Media $media): string
    {
        $model = $media->model;

        if ($model instanceof HasCollectionPaths) {
            $collectionPath = $model->getCollectionBasePath($media->collection_name, $media);

            if ($collectionPath !== null) {
                return rtrim($collectionPath, '/');
            }
        }

        return parent::getBasePath($media);
    }
}
