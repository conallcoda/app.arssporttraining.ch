<?php

namespace Coda\Cms\MediaEditing;

use Coda\Cms\MediaEditing\Contracts\MediaEditor;
use InvalidArgumentException;

class MediaEditorRegistry
{
    /** @var array<string, class-string<MediaEditor>> */
    protected array $editors = [];

    /** @param class-string<MediaEditor> $editorClass */
    public function register(string $key, string $editorClass): static
    {
        $this->editors[$key] = $editorClass;

        return $this;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->editors);
    }

    /** @return class-string<MediaEditor>|null */
    public function classFor(string $key): ?string
    {
        return $this->editors[$key] ?? null;
    }

    public function make(string $key): MediaEditor
    {
        $editorClass = $this->classFor($key);

        if ($editorClass === null) {
            throw new InvalidArgumentException("Unknown media editor [{$key}].");
        }

        return new $editorClass;
    }

    /** @return array<string, class-string<MediaEditor>> */
    public function all(): array
    {
        return $this->editors;
    }
}
