<?php

namespace Coda\Cms\Models\Observers;

use Coda\Cms\Models\Media;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Observers\MediaObserver as SpatieMediaObserver;

class MediaObserver extends SpatieMediaObserver
{
    public function deleted(SpatieMedia $media): void
    {
        if ($media instanceof Media && $media->isInPlace()) {
            return;
        }

        parent::deleted($media);
    }
}
