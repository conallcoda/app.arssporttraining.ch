<?php

namespace Coda\Cms\MediaEditing\Contracts;

use Coda\Cms\Models\Media;

interface MediaEditor
{
    public static function key(): string;

    public static function label(): string;

    public function view(): string;

    public function initialState(array $context = []): array;

    public function normalizeState(array $state): array;

    public function persist(Media $media, array $state): void;
}
