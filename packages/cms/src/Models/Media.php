<?php

namespace Coda\Cms\Models;

use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

class Media extends SpatieMedia
{
    protected $hidden = ['inline_content'];

    public function scopeWithInlineContent($query)
    {
        return $query;
    }

    public function getDecodedInlineContent(): mixed
    {
        if ($this->inline_content === null) {
            return null;
        }

        if ($this->mime_type === 'application/json') {
            return json_decode($this->inline_content, true);
        }

        return $this->inline_content;
    }

    public function isInPlace(): bool
    {
        return in_array($this->disk, config('cms.media.in_place_disks', []), true);
    }

    public function getShowUrl(): string
    {
        return route('cms.media.show', [
            'media' => $this->getKey(),
            'filename' => $this->file_name,
        ]);
    }
}
